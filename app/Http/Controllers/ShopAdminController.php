<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\SupplierOrder;
use App\Models\SupplierProduct;
use App\Models\SupplierUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopAdminController extends Controller
{

    /**
     * 设置活动商品
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function productActive(Request $request)
    {
        $id = intval($request->get("id", 0));
        $status = intval($request->get("status", 0));
        $commission = intval($request->get("commission", 0));

        if (!in_array($status, [1, 2])) {
            return $this->error("状态不正确");
        }

        if ($commission < 0 || $commission > 100) {
            return $this->error("扣率不正确");
        }

        if (!$product = SupplierProduct::query()->find($id)) {
            return $this->error("商品不存在");
        }
        $product->is_active = $status === 1 ? 1 : 0;
        $product->commission = $commission;
        $product->save();

        return $this->success();
    }

    /**
     * 商城后台-商品列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/7 3:35 下午
     */
    public function productList(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $search_key = $request->get("name", "");

        $query = SupplierProduct::query()->select(
            "id","depot_id","user_id","price","is_control","is_active","control_price","sale_count","status","stock",
            "sale_type","product_date","product_end_date","number","weight","detail","sort_admin","commission")
            ->whereHas("depot", function(Builder $query) use ($search_key) {
                if ($search_key) {
                    $query->where("name", "like", "%{$search_key}%");
                }
            })->with(["depot" => function ($query) {
                $query->select("id","cover","name","spec","unit","upc","manufacturer","term_of_validity","approval","generi_name");
            }, "user" => function ($query) {
                $query->select("id", "name");
            }]);

        $products = $query->orderBy("sort_admin")->orderBy("sort_supplier")->paginate($page_size);

        $result = [];

        if (!empty($products)) {
            foreach ($products as $product) {
                $tmp['id'] = $product->id;
                $tmp['price'] = $product->price;
                $tmp['is_control'] = $product->is_control;
                $tmp['commission'] = $product->commission;
                $tmp['is_active'] = $product->is_active;
                $tmp['control_price'] = $product->control_price;
                $tmp['stock'] = $product->stock;
                $tmp['sale_type'] = $product->sale_type;
                $tmp['sale_count'] = $product->sale_count;
                $tmp['status'] = $product->status;
                $tmp['number'] = $product->number;
                $tmp['product_date'] = $product->product_date;
                $tmp['product_end_date'] = $product->product_end_date;
                $tmp['detail'] = $product->detail;
                $tmp['weight'] = $product->weight;
                $tmp['cover'] = $product->depot->cover;
                $tmp['upc'] = $product->depot->upc;
                $tmp['name'] = $product->depot->name;
                $tmp['spec'] = $product->depot->spec;
                $tmp['unit'] = $product->depot->unit;
                $tmp['sort'] = $product->sort_admin;
                $tmp['manufacturer'] = $product->depot->manufacturer;
                $tmp['approval'] = $product->depot->approval;
                $tmp['term_of_validity'] = $product->depot->term_of_validity;
                $tmp['shop_name'] = $product->user->name ?? "";
                $result[] = $tmp;
            }
        }

        return $this->page($products, $result);
    }

    /**
     * 商城后台-商品排序
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/7 3:35 下午
     */
    public function productSort(Request $request)
    {
        $id = intval($request->get("id", 0));
        $sort = intval($request->get("sort", 0));

        if ($sort < 1) {
            return $this->error("排序不能小于1");
        }

        if (!$product = SupplierProduct::query()->find($id)) {
            return $this->error("商品不存在");
        }
        $product->sort_admin = $sort;
        $product->save();

        return $this->success();
    }

    /**
     * 商城后台-订单列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/11 9:55 上午
     */
    public function orderList(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $search_key = $request->get("search_key", '');
        $status = $request->get("status", null);
        $start_date = $request->get("start_date", '');
        $end_date = $request->get("end_date", '');

        $query = SupplierOrder::with(["shop", "items"])->orderBy("id", "desc");

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
                $order_info['frozen_fee'] = $order->frozen_fee;
                $order_info['product_fee'] = $order->product_fee;
                $order_info['payment_no'] = $order->payment_no;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['cancel_reason'] = $order->cancel_reason;
                $order_info['status'] = $order->status;
                $order_info['shop_name'] = $order->shop->name ?? "";
                $order_info['paid_at'] = $order->paid_at ? date("Y-m-d H:i:s", strtotime($order->paid_at)) : '-';
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

    /**
     * 商城后台-取消订单
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/11 9:55 上午
     */
    public function cancelOrder(Request $request)
    {
        $id = $request->get("id", 0);
        \Log::info("[采购后台-取消订单]-[订单ID: {$id}]");

        if (!$order = SupplierOrder::query()->find($id)) {
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
            $status = $order->status;
            \Log::info("[采购后台-取消订单]-[订单ID: {$id}]-[订单号: {$order->no}]-[订单状态1: {$status}]");
            // \Log::info("取消订单状态1", ['status=' . $status]);
            $order->status = 90;
            $order->cancel_reason = $request->get("cancel_reason") ?? '';
            $order->save();
            // \Log::info("取消订单状态2", ['status=' . $status]);
            \Log::info("[采购后台-取消订单]-[订单ID: {$id}]-[订单号: {$order->no}]-[订单状态2: {$status}]");
            if ($status === 30 && ($order->total_fee > 0)) {
                if ($receive_shop = Shop::query()->find($order->receive_shop_id)) {
                    if ($receive_shop_user = User::query()->find($receive_shop->own_id)) {
                        // \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        if (!User::query()->where(["id" => $receive_shop_user->id, "money" => $receive_shop_user->money])->update(["money" => $receive_shop_user->money + $order->total_fee])) {
                            // \Log::info("取消订单返款失败", ['order_id' => $order->id, 'money' => $order->total_fee]);
                            \Log::info("[采购后台-取消订单]-[订单ID: {$id}]-[订单号: {$order->no}]-[支付金额: {$order->total_fee}]-取消订单返款失败");
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

    /**
     * 管理后台-供货商列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/2/27 7:49 下午
     */
    public function supplierList(Request $request)
    {
        $page_size = intval($request->get("page_size", 10));
        $search_key = trim($request->get("search_key", ""));

        $query = SupplierUser::where("is_auth", 1);

        if ($search_key) {
            $query->where("name","like", "%{$search_key}%");
        }

        $data = $query->paginate($page_size);

        return $this->page($data);
    }
}
