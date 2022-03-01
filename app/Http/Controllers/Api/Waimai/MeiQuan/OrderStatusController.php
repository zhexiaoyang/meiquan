<?php

namespace App\Http\Controllers\Api\Waimai\MeiQuan;

use App\Http\Controllers\Controller;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    public $prefix = '[美团外卖服务商-订单状态回调]';

    public function own_delivery(Request $request)
    {
        $this->prefix .= '-[自配送]';

        if ($order_id = $request->get("order_view_id", "")) {
            $this->log('全部参数', $request->all());
        }

        if ($wm_order = WmOrder::where('order_id', $order_id)->first()) {
        }
        return json_encode(['data' => 'ok']);
    }
}
