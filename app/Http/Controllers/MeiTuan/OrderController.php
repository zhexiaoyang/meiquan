<?php

namespace App\Http\Controllers\MeiTuan;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController
{
    public function status(Request $request)
    {
        $res = ['code' => 1];
        $status = $request->get('status', '');
        $delivery_id = $request->get('delivery_id', 0);
        $data = $request->only(['courier_name', 'courier_phone', 'cancel_reason_id', 'cancel_reason','status']);
        if (($order = Order::where('delivery_id', $delivery_id)->first()) && in_array($status, [0, 20, 30, 50, 99])) {
            if ($status == 0) {
                $order->status = 30;

            } elseif ($status == 20) {
                $order->status = 50;

            } elseif ($status == 30) {
                $order->status = 60;

            } elseif ($status == 50) {
                $order->status = 70;

            } elseif ($status == 99) {
                $order->status = 99;
            }

            $order->courier_name = $data['courier_name'] ?? '';
            $order->courier_phone = $data['courier_phone'] ?? '';
            $order->cancel_reason_id = $data['cancel_reason_id'] ?? 0;
            $order->cancel_reason = $data['cancel_reason'] ?? '';
            if ($order->save()) {
                $res = ['code' => 0];
            }
        }
        \Log::info('订单状态回调', ['request' => $request, 'response' => $res]);
        return json_encode($res);
    }

    public function exception(Request $request)
    {
        $res = ['code' => 1];
        $delivery_id = $request->get('delivery_id', 0);
        $data = $request->only(['exception_id', 'exception_code', 'exception_descr', 'exception_time', 'courier_name', 'courier_phone']);
        if ($order = Order::where('delivery_id', $delivery_id)->first()) {
            $order->exception_id = $data['exception_id'];
            $order->exception_code = $data['exception_code'];
            $order->exception_descr = $data['exception_descr'];
            $order->exception_time = $data['exception_time'];
            $order->courier_name = $data['courier_name'];
            $order->courier_phone = $data['courier_phone'];
            if ($order->save()) {
                $res = ['code' => 0];
            }
        }
        \Log::info('订单异常回调', ['request' => $request, 'response' => $res]);
        return json_encode($res);
    }
}