<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Models\AddressCity;
use App\Models\Shop;
use App\Models\SupplierFreightCity;
use Illuminate\Http\Request;
use App\Models\SupplierCart;
use App\Models\SupplierOrder;

class SupplierOrderController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;

        $page_size = $request->get("page_size", 10);
        $query = SupplierOrder::with(['shop' => function($query) {
            $query->select("id","name");
        }, 'items'])->orderBy("id", "desc")->where("user_id", $user_id);

        $orders = $query->paginate($page_size);

        $_res = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_info['id'] = $order->id;
                $order_info['no'] = $order->no;
                $order_info['address'] = $order->address;
                $order_info['shipping_fee'] = $order->shipping_fee;
                $order_info['total_fee'] = $order->total_fee;
                $order_info['original_amount'] = $order->original_amount;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['cancel_reason'] = $order->cancel_reason;
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
                // 运费
                $postage = 0;
                // 总重量
                $product_weight = 0;
                // 总金额
                $total_fee = 0;
                // 创建一个订单
                $order   = new SupplierOrder([
                    'shop_id' => $shop_id,
                    'address' => [
                        'address' => $shop->shop_address,
                        'shop_id' => $shop->receive_shop_id,
                        'shop_name' => $shop->shop_name,
                        'meituan_id' => $shop->mt_shop_id,
                        'contact_name' => $shop->contact_name,
                        'contact_phone' => $shop->contact_phone,
                    ],
                    'receive_shop_id' => $shop->id,
                    'receive_shop_name' => $shop->shop_name,
                    'remark' => $remark,
                    'total_fee' => 0,
                ]);
                // 订单关联到当前用户
                $order->user()->associate($user);
                // 写入数据库
                $order->save();

                // 遍历购物车选中的商品
                foreach ($carts as $cart) {
                    $cart->load('product.depot');
                    // $product  = SupplierProduct::query()->find($data['product_id']);
                    $product = $cart->product;
                    $depot = $product->depot;
                    // 创建一个 OrderItem 并直接与当前订单关联
                    $item = $order->items()->make([
                        'amount' => $cart['amount'],
                        'price'  => $product->price,
                        'name'  => $depot->name,
                        'cover'  => $depot->cover,
                        'spec'  => $depot->spec,
                        'unit'  => $depot->unit,
                        'upc'  => $depot->upc,
                    ]);
                    $item->product()->associate($product->id);
                    $item->save();
                    $total_fee += $product->price * $cart['amount'];
                    $product_weight += $product->weight * $cart['amount'];

                    // 减库存
                    if ($cart->product->decreaseStock($cart['amount']) <= 0) {
                        throw new InvalidRequestException('该商品库存不足');
                    }
                }

                // 配送费计算
                if (($product_weight > 0) && ($shop_city_id = AddressCity::query()->where(['code' => $shop->citycode])->first())) {
                // if ($shop_city_id = AddressCity::query()->where(['code' => $shop->citycode])->first()) {
                    if ($freight = SupplierFreightCity::query()->where(['user_id' => $shop_id, 'city_code' => $shop_city_id->id])->first()) {
                        $first_weight = $freight->first_weight;
                        $continuation_weight = $freight->continuation_weight;
                        $weight1 = $freight->weight1;
                        $weight2 = $freight->weight2;

                        if ($product_weight / 1000 <= $weight1) {
                            $postage += $first_weight;
                        } else {
                            $postage += $first_weight;
                            $postage += ceil((($product_weight / 1000) - $weight1) / $weight2) * $continuation_weight;
                        }
                    }
                }
                // 计算配送费
                $total_fee += $postage;


                // 如果订单金额小于0，变成已支付状态
                if ($total_fee <= 0) {
                    // 更新支付状态
                    $order->update([
                        'status' => 30,
                        'paid_at' => date("Y-m-d"),
                        'payment_method' => 3
                    ]);
                } else {
                    // 写入配送费
                    $order->shipping_fee = $postage;
                    // 更新订单总金额
                    $order->total_fee = $total_fee;
                    // 保存信息
                    $order->save();
                }

                // 将下单的商品从购物车中移除
                $product_ids = collect($carts)->pluck('id');
                $user->carts()->whereIn('id', $product_ids)->delete();

                dispatch(new CloseOrder($order, 600));
            }

            return $order;
        });

        return $this->success(['order_id' => $order->id]);
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
        $order_info['total_fee'] = $order->total_fee;
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
                    $item_info['upc'] = $item->upc;
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
