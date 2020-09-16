<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use App\Models\SupplierCart;
use App\Models\SupplierOrder;

class SupplierOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $query = SupplierOrder::with(['shop' => function($query) {
            $query->select("id","name");
        }, 'items'])->orderBy("id", "desc");

        $orders = $query->paginate($page_size);

        $_res = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_info['id'] = $order->id;
                $order_info['no'] = $order->no;
                $order_info['address'] = $order->address;
                $order_info['shipping_fee'] = $order->shipping_fee;
                $order_info['total_amount'] = $order->total_amount;
                $order_info['original_amount'] = $order->original_amount;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['status'] = $order->status;
                $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));
                $order_info['shop_name'] = $order->shop->name ?? "";

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
                $_res[] = $order_info;
                $order_info = [];
            }
        }


        $res['list'] = $_res;
        $res['page'] = $orders->currentPage();
        $res['total'] = $orders->total();
        $res['page_total'] = $orders->lastPage();

        return $this->success($res);
    }

    public function store(Request $request)
    {
        $user  = $request->user();
        $shop_id = $request->get("shop_id", 0);
        $remark = $request->get("remark", "");

        if (!$shop = Shop::query()->where(["user_id" => $user->id, "id" => $shop_id])->first()) {
            return $this->error("请选择收货地址");
        }

        $carts = SupplierCart::with("product")->where([
            "user_id" => $user->id,
            "checked" => 1
        ])->get();

        if (empty($carts)) {
            return $this->error("请选择结算商品");
        }

        $data = [];

        foreach ($carts as $cart) {
            $data[$cart->product->user_id][] = $cart;
        }


        // 开启一个数据库事务
        $order = \DB::transaction(function () use ($user, $data, $shop, $remark) {

            foreach ($data as $shop_id => $carts) {
                \Log::info('message', [$shop_id]);
                // 创建一个订单
                $order   = new SupplierOrder([
                    'shop_id'       => $shop_id,
                    'address'       => [
                        'address'       => $shop->shop_address,
                        'contact_name'  => $shop->contact_name,
                        'contact_phone' => $shop->contact_phone,
                    ],
                    'remark'        => $remark,
                    'total_amount'  => 0,
                ]);
                // 订单关联到当前用户
                $order->user()->associate($user);
                // 写入数据库
                $order->save();

                $totalAmount = 0;
                // 遍历用户提交的 SKU
                foreach ($carts as $cart) {
                    $cart->load('product.depot');
                    // $product  = SupplierProduct::query()->find($data['product_id']);
                    $product = $cart->product;
                    $depot = $product->depot;
                    \Log::info('message', [$depot]);
                    // 创建一个 OrderItem 并直接与当前订单关联
                    $item = $order->items()->make([
                        'amount' => $cart['amount'],
                        'price'  => $product->price,
                        'name'  => $depot->name,
                        'cover'  => $depot->cover,
                        'spec'  => $depot->spec,
                        'unit'  => $depot->unit,
                    ]);
                    $item->product()->associate($product->id);
                    $item->save();
                    $totalAmount += $product->price * $cart['amount'];
                }

                // 更新订单总金额
                $order->update(['total_amount' => $totalAmount]);

                // 将下单的商品从购物车中移除
                $product_ids = collect($carts)->pluck('id');
                $user->carts()->whereIn('id', $product_ids)->delete();
            }

            return true;
        });

        return $this->success();
    }

    public function show(SupplierOrder $order)
    {
        $order->load(['shop' => function($query) {
            $query->select("id","name");
        }, 'items']);

        $order_info['id'] = $order->id;
        $order_info['no'] = $order->no;
        $order_info['address'] = $order->address;
        $order_info['shipping_fee'] = $order->shipping_fee;
        $order_info['total_amount'] = $order->total_amount;
        $order_info['original_amount'] = $order->original_amount;
        $order_info['payment_method'] = $order->payment_method;
        $order_info['status'] = $order->status;
        $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));
        $order_info['shop_name'] = $order->shop->name ?? "";

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

    public function received(SupplierOrder $order)
    {
        $order->status = 70;
        $order->save();
        return $this->success();
    }


}
