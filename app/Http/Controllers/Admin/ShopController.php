<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PrescriptionShopExport;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $search_key = $request->get("search_key", "");

        $query = Shop::select("id", "shop_name", "city");

        if ($search_key) {
            $query->where("shop_name", "like", "%{$search_key}%");
        }

        $shops = $query->orderByDesc("id")->get();

        return $this->success($shops);
    }

    public function update_three(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }

        $shop->mtwm = $request->get('mtwm', '');
        $shop->ele = $request->get('ele', '');
        $shop->jddj = $request->get('jddj', '');

        $shop->save();

        return $this->success($shop);
    }
}
