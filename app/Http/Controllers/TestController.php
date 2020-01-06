<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shop;

class TestController extends Controller
{

    public function arrange(Order $order)
    {
        $meituan = app("meituan");
        $result = $meituan->arrange([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id
        ]);

        if ($result['code'] === 0 && $order->update(['status' => 20])) {
            return $this->success([]);
        }

        return $this->error("测试失败");
    }

    public function shopStatus(Shop $shop)
    {
        $meituan = app("meituan");
        $result = $meituan->shopStatus([
            'shop_id' => $shop->shop_id,
            'status' => 30
        ]);

        if ($result['code'] === 0) {
            return $this->success([]);
        }

        return $this->error("测试失败");
    }

}
