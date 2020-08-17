<?php

namespace App\Http\Controllers;

use App\Models\SupplierCart;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;

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
}
