<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Models\ExpressOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KuaiDiController extends Controller
{
    public $prefix = '快递100订单回调';

    public function order(Request $request)
    {
        $this->log("全部参数", $request->all());
        if (!$order = ExpressOrder::where('order_id', $request->get('orderId', ''))->first()) {
            $this->log("订单不存在");
            return $this->status(null, 'success', 0);
        }
        $status = $request->get('status', '');
        $freight = $request->get('freight', '');
        $order->courier_name = $request->get('courierName', '');
        $order->courier_mobile = $request->get('courierMobile');
        $order->weight = $request->get('weight');
        $order->status = $status;
        if ($status == 10) {
            $order->freight = $freight;
            User::where('id', $order->user_id)->decrement('money', $freight);
            $this->log("减运费：{$freight} 元");
        }
        $order->save();

        return $this->status(null, 'success', 0);
    }
}
