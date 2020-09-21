<?php

namespace App\Http\Controllers\Supplier;

use App\Models\SupplierOrder;
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

        $query = SupplierOrder::with('items')->orderBy("id", "desc")->where("shop_id", $user->id);

        $orders = $query->paginate($page_size);

        $_res = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_info['id'] = $order->id;
                $order_info['no'] = $order->no;
                $order_info['address'] = $order->address;
                $order_info['shipping_fee'] = $order->shipping_fee;
                $order_info['total_amount'] = $order->total_amount;
                // $order_info['original_amount'] = $order->original_amount;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['status'] = $order->status;
                $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));

                $item_info = [];
                if (!empty($order->items)) {
                    foreach ($order->items as $item) {
                        if (isset($item->id)) {
                            $item_info['id'] = $item->product_id;
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
        $order_info['address'] = $order->address;
        $order_info['shipping_fee'] = $order->shipping_fee;
        $order_info['total_amount'] = $order->total_amount;
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
            'ship_platform' => 'bail|required|numeric',
        ],[
            'ship_no.required' => '物流单号不能为空',
            'ship_no.min' => '物流单号长度不能小于5',
            'ship_platform.required' => '物流平台不能为空',
            'ship_platform.numeric' => '物流平台不存在',
        ]);

        $order->deliver_at = date("Y-m-d H:i:s");
        $order->status = 50;
        $order->ship_no = $request->get("ship_no");
        $order->ship_platform = $request->get("ship_platform");
        $order->save();

        return $this->success();
    }
}