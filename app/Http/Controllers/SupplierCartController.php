<?php

namespace App\Http\Controllers;

use App\Models\AddressCity;
use App\Models\Shop;
use App\Models\SupplierCart;
use App\Models\SupplierFreightCity;
use App\Models\SupplierProduct;
use App\Models\SupplierUser;
use App\Traits\NoticeTool2;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierCartController extends Controller
{
    use NoticeTool2;
    public function index(Request $request)
    {
        $user = $request->user();
        $user_id = $user->id;
        $shop_id = $user->shop_id;

        if (!$shop = Shop::find($shop_id)) {
            return $this->error("没有认证的门店");
        }

        // 查询门店城市编码
        $city_code = AddressCity::where("code", $shop->citycode)->first();
        if (!isset($city_code->id)) {
            $this->ding_error("门店没有citycode|shop_id:{$shop_id}");
        }

        $result = [];
        $data = [];
        $total= 0;
        $status= false;

        $carts = SupplierCart::with(["product.depot" => function($query) {
            $query->select("id","cover","name","spec","unit");
        },"product.city_price" => function($query) use ($city_code) {
            $query->select("product_id", "price", "city_code")->where("city_code", $city_code->id ?? '');
        }])
            ->where("user_id", $user_id)
            ->whereHas("product", function ($query) use ($city_code) {
                $query->where('status', 20);
                $query->select("id", "price");$query->where("sale_type", 1)->orWhereHas("city_price", function(Builder $query) use ($city_code) {
                    $query->where("city_code", $city_code->id ?? '');
                });
            })->orderByDesc("id")->get();

        if (!empty($carts)) {
            $shop_cart_data = [];
            foreach ($carts as $cart) {
                $shop_cart_data[$cart->product->user_id][] = $cart;
            }
            foreach ($shop_cart_data as $shop_id => $shop_cart) {

                if (!$supplier = SupplierUser::select("id", "name", "starting")->where("online", 1)->find($shop_id)) {
                    continue;
                }
                $starting = $supplier->starting;

                $data[$shop_id]['shop'] = $supplier;

                $_total = 0;
                $_selected = false;
                foreach ($shop_cart as $item) {
                    if ($item->product->depot->id) {
                        $price = $item->product->city_price ? $item->product->city_price->price : $item->product->price;
                        $tmp['id'] = $item->id;
                        $tmp['is_active'] = $item->product->is_active ?? 0;
                        $tmp['name'] = $item->product->depot->name;
                        $tmp['cover'] = $item->product->depot->cover;
                        $tmp['spec'] = $item->product->depot->spec;
                        $tmp['unit'] = $item->product->depot->unit;
                        $tmp['number'] = $item->product->number;
                        $tmp['price'] = $price;
                        $tmp['price1'] = $item->product->price ?? 0;
                        $tmp['price0'] = $item->product->city_price ?? 0;
                        $tmp['product_date'] = $item->product->product_date;
                        $tmp['weight'] = $item->product->weight;
                        $tmp['amount'] = $item->amount;
                        $tmp['created_at'] = $item->product->created_at;
                        $tmp['checked'] = $item->checked;
                        $subtotal = $item->amount * ($price * 100) / 100;
                        $tmp['subtotal'] = $subtotal;
                        if ($item->checked) {
                            $_selected = true;
                            $_total += $subtotal * 100;
                        }
                        $data[$shop_id]['products'][] = $tmp;
                    }
                }
                if (($_total / 100 >= $starting) && $_selected) {
                    $status = true;
                }
                $supplier->total = $_total / 100;
                $total += $_total;
            }
        }

        $result['status'] = $status;
        $result['total'] = $total / 100;
        $result['data'] = array_values($data);

        return $this->success($result);
    }

    public function settlement(Request $request)
    {
        $user = $request->user();
        $user_id = $user->id;
        $shop_id = $user->shop_id;
        $user_frozen_money = $user->frozen_money ?? 0;

        // 判断是否有收货门店
        if (!$shop = Shop::find($shop_id)) {
            return $this->error("没有认证的门店");
        }
        // 城市编码
        $city_code = AddressCity::where("code", $shop->citycode)->first();
        if (!isset($city_code->id)) {
            $this->ding_error("门店没有citycode|shop_id:{$shop_id}");
        }

        // 判断是否直接购买流程
        $product_id = $request->get("product_id", 0);

        if ($product = SupplierProduct::find($product_id)) {

            SupplierCart::where("user_id", $user_id)->update(['checked' => 0]);

            if ($cart = SupplierCart::where(["user_id" => $user_id, 'product_id' => $product_id])->first()) {
                $cart->checked = 1;
                $cart->save();
            } else {
                $cart = new SupplierCart();
                $cart->user_id = $user_id;
                $cart->product_id = $product_id;
                $cart->amount = 1;
                $cart->save();
            }
        }

        // if ($shop_id) {
        //     if (!$shop = Shop::where('own_id', $user_id)->find($shop_id)) {
        //         return $this->error("收货门店不存在");
        //     }
        //
        //     if ($shop->auth !== 10) {
        //         return $this->error("收货门店未认证，不能下单");
        //     }
        // } else {
        //     $shop = Shop::where("user_id", $user_id)->orderBy("id", "asc")->first();
        // }

        // 返回数据
        $result = [];
        $data = [];
        $postage = 0;
        $total= 0;
        $frozen_money = 0;
        $total_weight= 0;

        // 购物车商品
        $carts = SupplierCart::with(["product.depot" => function($query) {
            $query->select("id","cover","name","spec","unit");
        },"product.city_price" => function($query) use ($city_code) {
            $query->select("product_id", "price", "city_code")->where("city_code", $city_code->id ?? '');
        }])
            ->where(["user_id" => $user_id, "checked" => 1])
            ->whereHas("product", function ($query) use ($city_code) {
                $query->select("product_id", "price");$query->where("sale_type", 1)->orWhereHas("city_price", function(Builder $query) use ($city_code) {
                    $query->where("city_code", $city_code->id ?? '');
                });
            })
            ->get();

        if (!empty($carts)) {
            $shop_cart_data = [];
            foreach ($carts as $cart) {
                $shop_cart_data[$cart->product->user_id][] = $cart;
            }
            foreach ($shop_cart_data as $shop_id => $shop_cart) {

                if (!$supplier = SupplierUser::select("id", "name", "starting")->where("online", 1)->find($shop_id)) {
                    continue;
                }

                $starting = $supplier->starting;
                $product_weight = 0;
                $product_postage = 0;
                $_total_weight = 0;
                $_frozen_money = 0;
                $_total = 0;

                foreach ($shop_cart as $item) {
                    if ($item->product->depot->id) {
                        $price = $item->product->city_price ? $item->product->city_price->price : $item->product->price;
                        $tmp['id'] = $item->id;
                        $tmp['is_active'] = $item->product->is_active ?? 0;
                        $tmp['name'] = $item->product->depot->name;
                        $tmp['cover'] = $item->product->depot->cover;
                        $tmp['spec'] = $item->product->depot->spec;
                        $tmp['unit'] = $item->product->depot->unit;
                        $tmp['number'] = $item->product->number;
                        $tmp['price'] = (float) $price;
                        $tmp['product_date'] = $item->product->product_date;
                        $tmp['weight'] = $item->product->weight;
                        $tmp['amount'] = $item->amount;
                        $tmp['created_at'] = $item->product->created_at;
                        $tmp['checked'] = $item->checked;
                        $subtotal = $item->amount * ($price * 100);
                        $tmp['subtotal'] = $subtotal / 100;

                        if ($item->checked) {
                            $_total += $subtotal;
                            $product_weight += $item->product->weight * $item->amount;
                            // $_total_weight += $item->product->weight * $item->amount;
                        }

                        if ($tmp['is_active'] === 1) {
                            $_frozen_money += $subtotal;
                        }

                        $data[$shop_id]['products'][] = $tmp;
                    }
                }

                if ($_total / 100 < $starting) {
                    unset($data[$shop_id]);
                    continue;
                }

                $total_weight = $total_weight * 100 + $_total_weight;
                $frozen_money += $_frozen_money;
                $total += $_total;

                if (($product_weight > 0) && ($shop_city_id = AddressCity::where(['code' => $shop->citycode])->first())) {
                    if ($freight = SupplierFreightCity::where(['user_id' => $shop_id, 'city_code' => $shop_city_id->id])->first()) {
                        $first_weight = $freight->first_weight * 100;
                        $continuation_weight = $freight->continuation_weight * 100;
                        $weight1 = $freight->weight1 * 1;
                        $weight2 = $freight->weight2 * 1;

                        if ($product_weight / 1000 <= $weight1) {
                            $product_postage += $first_weight;
                        } else {
                            $product_postage += $first_weight;
                            $product_postage += ceil((($product_weight / 1000) - $weight1) / $weight2) * $continuation_weight;
                        }
                    }
                }

                $supplier->postage = $product_postage / 100;
                $supplier->weight = $product_weight;
                $data[$shop_id]['shop'] = $supplier;
                $postage += $product_postage;
            }
        }

        $result['total'] = $total / 100;
        // $result['frozen_money'] = (min($user_frozen_money * 100 , $frozen_money) + $postage) / 100;
        $result['frozen_money'] = (min($user_frozen_money * 100 , $frozen_money)) / 100;
        $result['total_weight'] = $total_weight / 1000;
        $result['postage'] = $postage / 100;
        $result['address_id'] = $shop->id;
        $result['data'] = $data;

        return $this->success($result);
    }

    public function index2(Request $request)
    {
        $user_id = $request->user()->id;

        $address_id = $request->get("address_id");

        if ($address_id) {
            if (!$shop = Shop::where('own_id', $user_id)->find($address_id)) {
                return $this->error("收货门店不存在");
            }

            if ($shop->auth !== 10) {
                return $this->error("收货门店未认证，不能下单");
            }
        } else {
            $shop = Shop::where("user_id", $user_id)->orderBy("id", "asc")->first();
        }

        $result = [];
        $data = [];
        $postage = 0;
        $total= 0;
        $total_weight= 0;


        $carts = SupplierCart::with(["product.depot" => function($query) {
            $query->select("id","cover","name","spec","unit");
        }])->where("user_id", $user_id)->get();

        if (!empty($carts)) {
            $shop_cart_data = [];
            foreach ($carts as $cart) {
                $shop_cart_data[$cart->product->user_id][] = $cart;
            }
            foreach ($shop_cart_data as $shop_id => $shop_cart) {
                $product_weight = 0;
                foreach ($shop_cart as $item) {
                    if ($item->product->depot->id) {
                        $tmp['id'] = $item->id;
                        $tmp['name'] = $item->product->depot->name;
                        $tmp['cover'] = $item->product->depot->cover;
                        $tmp['spec'] = $item->product->depot->spec;
                        $tmp['unit'] = $item->product->depot->unit;
                        $tmp['number'] = $item->product->number;
                        $tmp['price'] = $item->product->price;
                        $tmp['product_date'] = $item->product->product_date;
                        $tmp['weight'] = $item->product->weight;
                        $tmp['amount'] = $item->amount;
                        $tmp['created_at'] = $item->product->created_at;
                        $tmp['checked'] = $item->checked;
                        $subtotal = $item->amount * ($item->product->price * 100) / 100;
                        $tmp['subtotal'] = $subtotal;
                        if ($item->checked) {
                            $total += $subtotal;
                            $product_weight += $item->product->weight * $item->amount;
                            $total_weight += $item->product->weight * $item->amount;
                        }
                        $data[] = $tmp;
                    }
                }

                // SupplierFreightCity::where('')
                // $product_weight

                if (($product_weight > 0) && ($shop_city_id = AddressCity::where(['code' => $shop->citycode])->first())) {
                    if ($freight = SupplierFreightCity::where(['user_id' => $shop_id, 'city_code' => $shop_city_id->id])->first()) {
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
            }
        }

        $result['total'] = $total;
        $result['total_weight'] = $total_weight / 1000;
        $result['postage'] = $postage;
        $result['address_id'] = $shop->id;
        $result['data'] = $data;

        return $this->success($result);
    }

    public function store(Request $request)
    {

        $product_id  = $request->get('product_id', 0);
        $amount = $request->get('amount', 0);
        $user = $request->user();

        if (!$product = SupplierProduct::find($product_id)) {
            return $this->error("商品不存在");
        }

        if ($cart = $user->carts()->where("product_id", $product_id)->first()) {
            $cart->update(["amount" => $cart->amount + $amount]);
        } else {
            $cart = new SupplierCart();
            $cart->user_id = $user->id;
            $cart->product_id = $product_id;
            $cart->amount = $amount;
            $cart->save();
        }

        return $this->success();
    }

    public function destroy(Request $request)
    {
        if ($cart = SupplierCart::where(['id' => $request->get("id", 0), "user_id" => Auth::id()])->first()) {
            $cart->delete();
        }

        return $this->success();
    }

    public function change(Request $request)
    {
        $num = $request->get("num", 0);

        if (!is_numeric($num) || $num < 1) {
            return $this->error("数量错误");
        }

        if (!$cart = SupplierCart::find($request->get("id", 0))) {
            return $this->error("购物车无此商品");
        }

        $cart->amount = $num;

        $cart->save();

        return $this->success();
    }

    public function checked(Request $request)
    {
        $all = $request->get("all");
        // $status = $request->get("status");

        // if (is_null($status) || !in_array($status, [0, 1])) {
        //     return $this->error("状态错误");
        // }

        if (isset($all) && $all === 1) {
            $user_id = Auth::id();
            SupplierCart::where("user_id", $user_id)->update(["checked" => 1]);

        } else {
            if (!$cart = SupplierCart::find($request->get("id", 0))) {
                return $this->error("购物车无此商品");
            }

            $cart->checked = !$cart->checked;

            $cart->save();
        }


        return $this->success();
    }

    /**
     * 查询购物车总商品数量
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/3 3:36 下午
     */
    public function number(Request $request)
    {
        $user = $request->user();
        $user_id = $user->id;
        $shop_id = $user->shop_id;
        $number= 0;

        if (!$shop = Shop::find($shop_id)) {
            return $this->success(['number' => $number]);
        }

        // 查询门店城市编码
        $city_code = AddressCity::where("code", $shop->citycode)->first();
        if (!isset($city_code->id)) {
            $this->ding_error("门店没有citycode|shop_id:{$shop_id}");
        }

        $carts = SupplierCart::with(["product.depot" => function($query) {
            $query->select("id","cover","name","spec","unit");
        },"product.city_price" => function($query) use ($city_code) {
            $query->select("product_id", "price", "city_code")->where("city_code", $city_code->id);
        }])
            ->where("user_id", $user_id)
            ->whereHas("product", function ($query) use ($city_code) {
                $query->select("id", "price");$query->where("sale_type", 1)->orWhereHas("city_price", function(Builder $query) use ($city_code) {
                    $query->where("city_code", $city_code->id);
                });
            })
            ->get();

        if (!empty($carts)) {
            $shop_cart_data = [];
            foreach ($carts as $cart) {
                $shop_cart_data[$cart->product->user_id][] = $cart;
            }
            foreach ($shop_cart_data as $shop_id => $shop_cart) {
                if (!$supplier = SupplierUser::select("id", "name", "starting")->where("online", 1)->find($shop_id)) {
                    continue;
                }
                foreach ($shop_cart as $item) {
                    if ($item->product->depot->id) {
                        $number += $item->amount;
                    }
                }
            }
        }

        return $this->success(['number' => $number]);
    }
}
