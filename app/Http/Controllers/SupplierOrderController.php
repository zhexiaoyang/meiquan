<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Models\AddressCity;
use App\Models\Shop;
use App\Models\SupplierFreightCity;
use App\Models\SupplierProductCityPriceItem;
use App\Models\User;
use App\Models\UserFrozenBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Models\SupplierCart;
use App\Models\SupplierOrder;
use Illuminate\Support\Facades\Auth;

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
        $user = $request->user();
        $user_id = $user->id;
        $shop_id = $user->shop_id;
        $remark = $request->get("remark", "");
        $frozen_status = $request->get("frozen_status", 0);

        \Log::info("[商城订单-创建订单]-[用户ID：{$user->id}]-全部参数", $request->all());

        // 判断是否有收货门店
        if (!$shop = Shop::query()->find($shop_id)) {
            \Log::info("[商城订单-创建订单]-没有认证的门店");
            return $this->error("没有认证的门店");
        }
        // 城市编码
        $city_code = AddressCity::query()->where("code", $shop->citycode)->first();

        // 购物车商品
        $carts = SupplierCart::with(["product.depot" => function($query) {
            $query->select("id","cover","name","spec","unit");
        },"product.city_price" => function($query) use ($city_code) {
            $query->select("product_id", "price", "city_code")->where("city_code", $city_code->id);
        }])
            ->where(["user_id" => $user_id, "checked" => 1])
            ->whereHas("product", function ($query) use ($city_code) {
                $query->select("product_id", "price");$query->where("sale_type", 1)->orWhereHas("city_price", function(Builder $query) use ($city_code) {
                    $query->where("city_code", $city_code->id);
                });
            })
            ->get();

        $data = [];

        foreach ($carts as $cart) {
            $data[$cart->product->user_id][] = $cart;
        }

        unset($carts);

        // 开启一个数据库事务
        $res = \DB::transaction(function () use ($user, $data, $shop, $remark, $frozen_status) {
            \Log::info("[商城订单-创建订单]-开启一个数据库事务");

            $pay_no = SupplierOrder::findAvailablePayNo();

            // 是否需要支付
            $pay_status = 0;
            foreach ($data as $shop_id => $carts) {
                // 活动商品金额（冻结金额）
                $frozen_money = 0;
                // 订单使用冻结金额
                $use_frozen_money = 0;
                // 运费
                $postage = 0;
                // 总重量
                $product_weight = 0;
                // 总金额
                $total_fee = 0;
                // 创建一个订单
                $order   = new SupplierOrder([
                    'shop_id' => $shop_id,
                    'pay_no' => $pay_no,
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
                    // 商品价格
                    $price = $cart->product->city_price ? $cart->product->city_price->price : $cart->product->price;
                    // 商品信息
                    $product = $cart->product;
                    // 品库信息
                    $depot = $product->depot;

                    // 创建一个 OrderItem 并直接与当前订单关联
                    $item = $order->items()->make([
                        'amount' => $cart['amount'],
                        'price'  => $price,
                        'name'  => $depot->name,
                        'cover'  => $depot->cover,
                        'spec'  => $depot->spec,
                        'unit'  => $depot->unit,
                        'upc'  => $depot->upc,
                    ]);
                    $item->product()->associate($product->id);
                    $item->save();
                    $product->sale_count += $cart['amount'];
                    $product->save();
                    $total_fee += ($price * 100) * $cart['amount'];
                    $product_weight += $product->weight * $cart['amount'];

                    if ($product->is_active === 1) {
                        $frozen_money += ($price * 100) * $cart['amount'];
                    }

                    // 减库存
                    if ($cart->product->decreaseStock($cart['amount']) <= 0) {
                        throw new InvalidRequestException($depot->name . '，商品库存不足', 200);
                    }
                }

                // 配送费计算
                if (($product_weight > 0) && ($shop_city_id = AddressCity::query()->where(['code' => $shop->citycode])->first())) {
                // if ($shop_city_id = AddressCity::query()->where(['code' => $shop->citycode])->first()) {
                    if ($freight = SupplierFreightCity::query()->where(['user_id' => $shop_id, 'city_code' => $shop_city_id->id])->first()) {
                        $first_weight = $freight->first_weight * 100;
                        $continuation_weight = $freight->continuation_weight * 100;
                        $weight1 = $freight->weight1 * 1;
                        $weight2 = $freight->weight2 * 1;

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
                \Log::info("[商城订单-创建订单]-配送费：{$postage}");

                // 配送费算冻结金额
                // $frozen_money += $postage;

                // 是否使用冻结余额
                if ($frozen_status > 0 && $frozen_money > 0) {
                    \Log::info("[商城订单-创建订单]-商品冻结余额：{$frozen_money}(分)-使用冻结余额");
                    $orderUser = User::find($user->id);
                    \Log::info("[商城订单-创建订单]-用户冻结余额：{$user->frozen_money}(元)-使用冻结余额");
                    $use_frozen_money = min($frozen_money, $user->frozen_money * 100);
                    \Log::info("[商城订单-创建订单]-使用冻结余额：{$use_frozen_money}(分)-使用冻结余额");

                    if ($use_frozen_money > 0) {
                        User::where([
                            "id" => $user->id,
                            "frozen_money" => $orderUser->frozen_money
                        ])->update(["frozen_money" => (($orderUser->frozen_money * 100 - $use_frozen_money) / 100)]);
                        $logs = new UserFrozenBalance([
                            "user_id" => $user->id,
                            "money" => $use_frozen_money / 100,
                            "type" => 2,
                            "before_money" => $orderUser->frozen_money,
                            "after_money" => ($orderUser->frozen_money * 100 - $use_frozen_money) / 100,
                            "description" => "商城订单：{$order->no}",
                            "tid" => $order->id
                        ]);
                        $logs->save();
                    }
                }

                // 如果订单金额小于0，变成已支付状态
                $order->status = 0;
                if (($total_fee - $use_frozen_money) <= 0) {
                    \Log::info("[商城订单-创建订单]-使用冻结余额：{$total_fee}(分)-使用冻结余额：{$use_frozen_money}(分)-总金额减冻结金额大于小于等于0");
                    // 更新支付状态
                    $order->status = 30;
                    $order->paid_at = date("Y-m-d");
                    $order->payment_method = 30;
                } else  {
                    $pay_status = 1;
                }

                // 写入配送费
                $order->shipping_fee = $postage / 100;
                // 更新订单总金额
                $order->total_fee = $total_fee / 100;

                if (count($data) <= 1) {
                    $order->pay_no = $order->no;
                }
                // 余额支付金额
                $order->frozen_fee = $use_frozen_money / 100;
                // 保存信息
                $order->save();

                // 将下单的商品从购物车中移除
                $product_ids = collect($carts)->pluck('id');
                $user->carts()->whereIn('id', $product_ids)->delete();

                dispatch(new CloseOrder($order, 600));
            }

            // $order->status = $pay_status;
            // return $order;
            return ['id' => $order->id, 'no' => $order->pay_no, 'status' => $pay_status];
        });

        // return $this->success(['id' => $order->id, 'no' => $order->pay_no, 'status' => $order->status]);
        return $this->success($res);
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
        $order_info['ship_no'] = $order->ship_no;
        $order_info['ship_platform'] = $order->ship_platform;
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

    public function payOrders(Request $request)
    {
        $user = Auth::user();
        $id = $request->get("id", 0);
        $no = $request->get("no", 0);
        $amount = 0;
        $created_at = '';
        $orders = [];

        if ($id) {
            $orders = SupplierOrder::query()
                ->select("id", "no", "total_fee", "frozen_fee", "created_at")
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->get();
        } elseif ($no) {
            $orders = SupplierOrder::query()
                ->select("id", "no", "total_fee", "frozen_fee", "created_at")
                ->where('pay_no', $no)
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->get();
        }

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $created_at = date("Y-m-d H:i:s", strtotime($order->created_at));
                $amount += $order->total_fee * 100 - $order->frozen_fee * 100;
            }

            $amount = $amount / 100;
        }

        $result = [
            "amount" => $amount,
            "money" => $user->money,
            "created_at" => $created_at,
            "orders" => $orders
        ];

        return $this->success($result);
    }


}
