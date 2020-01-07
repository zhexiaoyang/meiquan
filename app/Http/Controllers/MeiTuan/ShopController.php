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
        $res = ['code' => 1];
        if (($shop = Shop::where('shop_id', $shop_id)->first()) && in_array($status, [10, 20, 30, 40])) {
            $shop->status = $status;
            if ($shop->save()) {
                $res = ['code' => 0];
            }
        }
        \Log::info('门店状态回调', ['status' => $status, 'shop_id' => $shop_id, 'res' => $res]);
        return json_encode($res);
    }
}