<?php

namespace App\Http\Controllers;

use App\Models\AddressCity;
use App\Models\Shop;
use App\Models\SupplierCart;
use App\Models\SupplierFreightCity;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierCartController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;

        $address_id = $request->get("address_id");

        if ($address_id) {
            if (!$shop = Shop::query()->where('own_id', $user_id)->find($address_id)) {
                return $this->error("收货门店不存在");
            }

            if ($shop->auth !== 10) {
                return $this->error("收货门店未认证，不能下单");
            }
        } else {
            $shop = Shop::query()->where("user_id", $user_id)->orderBy("id", "asc")->first();
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

                // SupplierFreightCity::query()->where('')
                // $product_weight

                if (($product_weight > 0) && ($shop_city_id = AddressCity::query()->where(['code' => $shop->citycode])->first())) {
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

        if (!$product = SupplierProduct::query()->find($product_id)) {
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
        if ($cart = SupplierCart::query()->where(['id' => $request->get("id", 0), "user_id" => Auth::id()])->first()) {
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

        if (!$cart = SupplierCart::query()->find($request->get("id", 0))) {
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
            SupplierCart::query()->where("user_id", $user_id)->update(["checked" => 1]);

        } else {
            if (!$cart = SupplierCart::query()->find($request->get("id", 0))) {
                return $this->error("购物车无此商品");
            }

            $cart->checked = !$cart->checked;

            $cart->save();
        }


        return $this->success();
    }
}
