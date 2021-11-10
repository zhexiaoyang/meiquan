<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class MinKangOrderController extends Controller
{
    public function statusMp(Request $request)
    {
        $order_id = $request->get('order_view_id', '');
        $status = $request->get('logistics_status', '');

        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            if ($status == 20) {
                $order->status = 7;
                $order->save();
            }
        }
        \Log::info("[美团外卖-民康回调-订单配送状态回调-美配]", $request->all());
        return json_encode(['data' => 'ok']);
    }

    public function statusZp(Request $request)
    {
        $order_id = $request->get('order_view_id', '');
        $status = $request->get('logistics_status', '');

        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            if ($status == 20) {
                $order->status = 7;
                $order->save();
            }
        }
        \Log::info("[美团外卖-民康回调-订单配送状态回调-自配]", $request->all());
        return json_encode(['data' => 'ok']);
    }

    public function complete(Request $request)
    {
        $order_id = $request->get('order_view_id', '');
        $status = $request->get('status', '');

        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            if ($status == 8) {
                $order->status = 8;
                $order->save();
            }
        }
        \Log::info("[美团外卖-民康回调-订单完成]", $request->all());
        return json_encode(['data' => 'ok']);
    }
}
