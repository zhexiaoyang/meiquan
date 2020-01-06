<?php

namespace App\Http\Controllers\MeiTuan;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController
{
    public function status(Request $request)
    {
        $status = $request->get('status', '');
        $shop_id = $request->get('shop_id', 0);
        if (($shop = Shop::where('shop_id', $shop_id)->first()) && in_array($status, [10, 20, 30, 40])) {
            $shop->status = $status;
            if ($shop->save()) {
                return json_encode(['code' => 0]);
            }
        }
        return json_encode(['code' => 1]);
    }
}