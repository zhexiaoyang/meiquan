<?php

namespace App\Http\Controllers;

use App\Models\ContractOrder;
use App\Models\OnlineShop;
use App\Models\Order;
use App\Models\Shop;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function card(Request $request)
    {
        $order_query = Order::query()->where("over_at", ">", date("Y-m-d"))->where("status", 70);
        $shop_query = Shop::query()->where("status", 40);
        $online_query = OnlineShop::query()->where("status", 40);
        $supplier_query = SupplierOrder::query()->whereIn("status", [30, 50]);

        // 判断可以查询的药店
        if (!$request->user()->hasRole('super_man')) {
            $order_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $shop_query->whereIn('id', $request->user()->shops()->pluck('id'));
            $online_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $supplier_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $res = [
            "order" => $order_query->count(),
            "shop" => $shop_query->count(),
            "online" => $online_query->count(),
            "supplier" => $supplier_query->count(),
        ];

        return $this->success($res);
    }

    public function contract(Request $request)
    {
        $number = ContractOrder::where(
            ["user_id" => $request->user()->id],
            ['online_shop_id' => 0]
        )->count();

        return $this->status(compact("number"));
    }
}
