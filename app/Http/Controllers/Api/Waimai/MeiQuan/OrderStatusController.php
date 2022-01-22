<?php

namespace App\Http\Controllers\Api\Waimai\MeiQuan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    public function own_delivery(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[订单状态-自配送]-全部参数：", $request->all());
        return json_encode(['data' => 'ok']);
    }
}
