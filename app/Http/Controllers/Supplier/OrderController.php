<?php

namespace App\Http\Controllers\Supplier;

use App\Exports\SupplierOrderProductsExport;
use App\Exports\SupplierOrdersExport;
use App\Models\Shop;
use App\Models\ShopAuthentication;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Models\UserFrozenBalance;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * 订单列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $page_size = $request->get("page_size", 10);

        $search_key = $request->get("search_key", '');
        $status = $request->get("status", null);
        $start_date = $request->get("start_date", '');
        $end_date = $request->get("end_date", '');

        $query = SupplierOrder::with('items')->orderBy("id", "desc")
            ->where("shop_id", $user->id)
            ->where("status", '>', 0);

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('no', 'like', "%{$search_key}%");
                $query->orWhere('receive_shop_name', 'like', "%{$search_key}%");
            });
        }

        if (!is_null($status)) {
            $query->where("status", $status);
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $orders = $query->paginate($page_size);

        $_res = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_info['id'] = $order->id;
                $order_info['no'] = $order->no;
                $order_info['address'] = $order->address;
                $order_info['shipping_fee'] = $order->shipping_fee;
                $order_info['total_fee'] = $order->total_fee;
                $order_info['product_fee'] = $order->product_fee;
                $order_info['pay_charge_fee'] = $order->pay_charge_fee;
                $order_info['mq_charge_fee'] = $order->mq_charge_fee;
                // $order_info['profit_fee'] = $order->profit_fee;
                $order_info['invoice'] = $order->invoice;
                // $order_info['original_amount'] = $order->original_amount;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['cancel_reason'] = $order->cancel_reason;
                $order_info['status'] = $order->status;
                $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));
                // 结算金额（js有精度问题，放到程序里面做）
                $profit_fee = $order->total_fee - $order->mq_charge_fee;
                if ($order->payment_method !==0 && $order->payment_method !== 30) {
                    $profit_fee -= $order->pay_charge_fee;
                } else {
                    $order_info['pay_charge_fee'] = 0;
                }
                $order_info['profit_fee'] = (float) sprintf("%.2f",$profit_fee);

                $item_info = [];
                if (!empty($order->items)) {
                    foreach ($order->items as $item) {
                        if (isset($item->id)) {
                            $item_info['id'] = $item->id;
                            $item_info['product_id'] = $item->product_id;
                            $item_info['name'] = $item->name;
                            $item_info['upc'] = $item->upc;
                            $item_info['cover'] = $item->cover;
                            $item_info['spec'] = $item->spec;
                            $item_info['unit'] = $item->unit;
                            $item_info['amount'] = $item->amount;
                            $item_info['price'] = $item->price;
                            $item_info['commission'] = $item->commission . "%";
                            $item_info['mq_charge_fee'] = $item->mq_charge_fee;
                            $order_info['items'][] = $item_info;
                        }
                    }
                }
                $_res[] = $order_info;
                $order_info = [];
            }
        }

        return $this->page($orders, $_res);
    }

    /**
     * 导出订单
     * @param Request $request
     * @param SupplierOrdersExport $supplierOrdersExport
     * @return SupplierOrdersExport
     * @author zhangzhen
     * @data dateTime
     */
    public function export(Request $request, SupplierOrdersExport $supplierOrdersExport)
    {
        return $supplierOrdersExport->withRequest($request);
    }

    /**
     * 导出订单商品
     * @param Request $request
     * @param SupplierOrderProductsExport $supplierOrderProductsExport
     * @return SupplierOrderProductsExport
     * @author zhangzhen
     * @data dateTime
     */
    public function exportProduct(Request $request, SupplierOrderProductsExport $supplierOrderProductsExport)
    {
        return $supplierOrderProductsExport->withRequest($request);
    }

    /**
     * 订单详情
     * @param Request $request
     * @return mixed
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$order = SupplierOrder::query()->where("shop_id", $user->id)->find($request->get("id", 0))) {
            return $this->error("订单不存在");
        }

        $order->load('items');

        $order_info['id'] = $order->id;
        $order_info['no'] = $order->no;
        $order_info['ship_no'] = $order->ship_no;
        $order_info['ship_platform'] = $order->ship_platform;
        $order_info['deliver_at'] = $order->deliver_at;
        $order_info['address'] = $order->address;
        $order_info['shipping_fee'] = $order->shipping_fee;
        $order_info['total_fee'] = $order->total_fee;
        $order_info['original_amount'] = $order->original_amount;
        $order_info['payment_method'] = $order->payment_method;
        $order_info['status'] = $order->status;
        $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));
        $order_info['paid_at'] = $order->paid_at ? date("Y-m-d H:i:s", strtotime($order->paid_at)) : "";
        $order_info['deliver_at'] = $order->deliver_at ? date("Y-m-d H:i:s", strtotime($order->deliver_at)): "";

        $item_info = [];
        if (!empty($order->items)) {
            foreach ($order->items as $item) {
                if (isset($item->id)) {
                    $item_info['id'] = $item->product_id;
                    $item_info['name'] = $item->name;
                    $item_info['cover'] = $item->cover;
                    $item_info['spec'] = $item->spec;
                    $item_info['unit'] = $item->unit;
                    $item_info['amount'] = $item->amount;
                    $item_info['price'] = $item->price;
                    $order_info['items'][] = $item_info;
                }
            }
        }

        return $this->success($order_info);
    }

    /**
     * 资质列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/27 6:21 下午
     */
    public function qualifications(Request $request)
    {
        $user = Auth::user();

        if (!$order = SupplierOrder::query()->where("shop_id", $user->id)->find($request->get("id", 0))) {
            return $this->error("订单不存在");
        }

        $qualifications = ShopAuthentication::select("yyzz","chang","yyzz_start_time","yyzz_end_time","xkz","ypjy_start_time","ypjy_end_time","spjy","spjy_start_time","spjy_end_time","ylqx","ylqx_start_time","ylqx_end_time","elqx","sfz","wts")
            ->where("shop_id", $order->receive_shop_id)->first();

        return $this->success($qualifications);
    }

    public function receiveQualification(Request $request)
    {
        $user = Auth::user();

        if (!$order = SupplierOrder::query()->where("shop_id", $user->id)->find($request->get("id", 0))) {
            return $this->error("订单不存在");
        }

        return $this->success();
    }

    /**
     * 发货
     * @param Request $request
     * @return mixed
     */
    public function deliver(Request $request)
    {
        $user = Auth::user();

        if (!$order = SupplierOrder::query()->where("shop_id", $user->id)->find($request->get("id", 0))) {
            return $this->error("订单不存在");
        }

        $request->validate([
            'ship_no' => 'bail|required|min:5',
            'ship_platform' => 'bail|required',
        ],[
            'ship_no.required' => '物流单号不能为空',
            'ship_no.min' => '物流单号长度不正确',
            'ship_platform.required' => '物流平台不能为空',
            // 'ship_platform.numeric' => '物流平台不存在',
        ]);

        $order->deliver_at = date("Y-m-d H:i:s");
        $order->status = 50;
        $order->ship_no = $request->get("ship_no");
        $order->ship_platform = $request->get("ship_platform");
        $order->save();

        return $this->success();
    }

    /**
     * 取消订单
     * @param Request $request
     * @return mixed
     */
    public function cancel(Request $request)
    {
        $user = Auth::user();
        $id = $request->get("id", 0);
        $reason = $request->get("cancel_reason") ?? '商家取消';

        if (!$order = SupplierOrder::query()->where("shop_id", $user->id)->find($id)) {
            return $this->error("订单不存在");
        }

        if ($order->status === 50) {
            return $this->error("订单已发货不能取消");
        }

        if ($order->status === 70) {
            return $this->error("订单已完成不能取消");
        }

        if ($order->status !== 30) {
            return $this->error("订单状态不正确，不能取消");
        }

        if ($order->status === 30) {
            try {
                DB::transaction(function () use ($order, $reason) {
                    DB::table('supplier_orders')->where("id", $order->id)->update([
                        'status' => 90,
                        'cancel_reason' => $reason
                    ]);
                    if ($order->frozen_fee) {
                        if ($user = User::query()->find($order->user_id)) {
                            // 取消订单-商城余额支付部分返回到商城余额
                            if ($order->frozen_fee > 0) {
                                DB::table('users')->where('id', $order->user_id)->increment('frozen_money', $order->frozen_fee);
                                $logs = new UserFrozenBalance([
                                    "user_id" => $order->user_id,
                                    "money" => $order->frozen_fee,
                                    "type" => 1,
                                    "before_money" => $user->frozen_money,
                                    "after_money" => $user->frozen_money + $order->frozen_fee,
                                    "description" => "商城订单取消(余额)：{$order->no}",
                                    "tid" => $order->id
                                ]);
                                $logs->save();
                            }
                            // DB::table('users')->where('id', $order->user_id)->increment('frozen_money', $order->frozen_fee);
                            // $logs = new UserFrozenBalance([
                            //     "user_id" => $order->user_id,
                            //     "money" => $order->frozen_fee,
                            //     "type" => 1,
                            //     "before_money" => $user->frozen_money,
                            //     "after_money" => $user->frozen_money + $order->frozen_fee,
                            //     "description" => "商城订单取消(余额)：{$order->no}",
                            //     "tid" => $order->id
                            // ]);
                            // $logs->save();
                        }
                    }
                    if ($order->pay_fee) {
                        if ($user = User::query()->find($order->user_id)) {
                            // 取消订单-支付部分返回到商城余额
                            // DB::table('users')->where('id', $order->user_id)->increment('frozen_money', $order->pay_fee);
                            // $logs = new UserFrozenBalance([
                            //     "user_id" => $order->user_id,
                            //     "money" => $order->pay_fee,
                            //     "type" => 1,
                            //     "before_money" => $user->frozen_money,
                            //     "after_money" => $user->frozen_money + $order->pay_fee,
                            //     "description" => "商城订单取消(支付)：{$order->no}",
                            //     "tid" => $order->id
                            // ]);
                            // $logs->save();
                            // 微信支付原路返回
                            if ($order->payment_no) {
                                $order = [
                                    'transaction_id' => $order->payment_no,
                                    'out_refund_no' => $order->no,
                                    'refund_fee' => intval($order->pay_fee * 100),
                                    'total_fee' => intval($order->pay_fee * 100),
                                    "refund_desc" => "取消订单"
                                ];
                                $wechatOrder = app('pay.wechat_supplier')->refund($order);
                                \Log::info("商家-微信退款-返回参数", [$wechatOrder]);
                            }
                        }
                    }
                });
            } catch (\Exception $e) {
                $message = [
                    $e->getCode(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage()
                ];
                \Log::info("[商家后台-取消订单-事务提交失败]-[订单ID: {$id}]", $message);
                return $this->error("操作失败，请稍后再试");
            }
            foreach ($order->items as $item) {
                $item->product->addStock($item->amount);
            }
        }

        // if ($order->status <= 30) {
        //     $status = $order->status;
        //     \Log::info("取消订单状态1", ['status=' . $status]);
        //     $order->status = 90;
        //     $order->cancel_reason = $request->get("cancel_reason") ?? '';
        //     $order->save();
        //     \Log::info("取消订单状态2", ['status=' . $status]);
        //     if ($status === 30 && ($order->total_fee > 0)) {
        //         if ($receive_shop = Shop::query()->find($order->receive_shop_id)) {
        //             if ($receive_shop_user = User::query()->find($receive_shop->own_id)) {
        //                 if (!User::query()->where(["id" => $receive_shop_user->id, "money" => $receive_shop_user->money])->update(["money" => $receive_shop_user->money + $order->total_fee])) {
        //                     \Log::info("取消订单返款失败", ['order_id' => $order->id, 'money' => $order->total_fee]);
        //                 }
        //             }
        //         }
        //     }
        //     foreach ($order->items as $item) {
        //         $item->product->addStock($item->amount);
        //     }
        // }

        return $this->success();
    }

    public function express()
    {
        $data = [
            [ 'id' => 1, 'name' => '极兔速递'],
            [ 'id' => 2, 'name' => '顺丰快递'],
            [ 'id' => 3, 'name' => '申通快递'],
            [ 'id' => 4, 'name' => '中通快递'],
            [ 'id' => 5, 'name' => '圆通快递'],
            [ 'id' => 6, 'name' => '韵达快递'],
            [ 'id' => 7, 'name' => '百世快递'],
            [ 'id' => 8, 'name' => '德邦快递'],
            [ 'id' => 9, 'name' => '天天快递'],
            [ 'id' => 10, 'name' => 'EMS快递'],
        ];

        return $this->success($data);
    }

    /**
     * 可开发票订单
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/27 6:28 下午
     */
    public function invoice()
    {
        $user = Auth::user();
        $orders = SupplierOrder::query()->select("id", "no", "pay_charge_fee", "mq_charge_fee")
            ->where("shop_id", $user->id)
            ->where("status", 70)->orderBy("id", "desc")->get();

        return $this->success($orders);
    }
}
