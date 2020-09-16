<?php

namespace App\Http\Controllers;

use App\Models\SupplierCart;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierCartController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size");

        $user_id = $request->user()->id;

        $carts = SupplierCart::with(["product.depot" => function($query) {
            $query->select("id","cover","name","spec","unit");
        }])->where("user_id", $user_id)->paginate($page_size);

        return $this->page($carts);
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
