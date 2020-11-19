<?php

namespace App\Http\Controllers\Supplier;

use App\Exports\SupplierOrderProductsExport;
use App\Exports\SupplierOrdersExport;
use App\Models\Shop;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

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
                // $order_info['original_amount'] = $order->original_amount;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['cancel_reason'] = $order->cancel_reason;
                $order_info['status'] = $order->status;
                $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));

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

    public function export(Request $request, SupplierOrdersExport $supplierOrdersExport)
    {
        return $supplierOrdersExport->withRequest($request);
    }

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

        if (!$order = SupplierOrder::query()->where("shop_id", $user->id)->find($request->get("id", 0))) {
            return $this->error("订单不存在");
        }

        if ($order->status === 50) {
            return $this->error("订单已发货不能取消");
        }

        if ($order->status === 70) {
            return $this->error("订单已完成不能取消");
        }

        if ($order->status <= 30) {
            $status = $order->status;
            \Log::info("取消订单状态1", ['status=' . $status]);
            $order->status = 90;
            $order->cancel_reason = $request->get("cancel_reason") ?? '';
            $order->save();
            \Log::info("取消订单状态2", ['status=' . $status]);
            if ($status === 30 && ($order->total_fee > 0)) {
                if ($receive_shop = Shop::query()->find($order->receive_shop_id)) {
                    if ($receive_shop_user = User::query()->find($receive_shop->own_id)) {
                        if (!User::query()->where(["id" => $receive_shop_user->id, "money" => $receive_shop_user->money])->update(["money" => $receive_shop_user->money + $order->total_fee])) {
                            \Log::info("取消订单返款失败", ['order_id' => $order->id, 'money' => $order->total_fee]);
                        }
                    }
                }
            }
            foreach ($order->items as $item) {
                $item->product->addStock($item->amount);
            }
        }

        return $this->success();
    }
}
