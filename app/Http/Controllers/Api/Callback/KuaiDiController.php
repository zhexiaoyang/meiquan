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
        $param = json_decode($request->get('param'), true);
        $data = json_decode($param['data'], true);
        if (!$order = ExpressOrder::where('order_id', $request->get('orderId', ''))->first()) {
            $this->log("订单不存在");
            return $this->status(null, 'success', 0);
        }
        $status = $data['status'];
        $freight = $data['freight'];
        $courier_name = $data['courierName'];
        if ($courier_name != null) {
            $order->courier_name = $courier_name;
        }
        $courier_mobile = $data['courierMobile'];
        if ($courier_mobile != null) {
            $order->courier_mobile = $courier_mobile;
        }
        $weight = $data['weight'];
        if ($weight != null) {
            $order->weight = $weight;
        }
        $order->status = $status;
        if ($status == 10) {
            if ($freight != null) {
                $order->freight = $freight;
                User::where('id', $order->user_id)->decrement('money', $freight);
                $this->log("减运费：{$freight} 元");
            } else {
                $this->log("运费为null");
            }
        }
        $order->save();

        return $this->status(null, 'success', 0);
    }
}
