<?php

namespace App\Http\Controllers\MeiTuan;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController
{
    public function status(Request $request)
    {
        \Log::info('message', $request);
        $result = [];
        $status = $request->get('status', '');
        $delivery_id = $request->get('delivery_id', 0);
        $data = $request->only(['courier_name', 'courier_phone', 'cancel_reason_id', 'cancel_reason','status']);
        if (($order = Order::where('delivery_id', $delivery_id)->first()) && in_array($status, [0, 20, 30, 50, 99])) {
            $order->status = $data['status'];
            $order->courier_name = $data['courier_name'];
            $order->courier_phone = $data['courier_phone'];
            $order->cancel_reason_id = $data['cancel_reason_id'];
            $order->cancel_reason = $data['cancel_reason'];
            if ($order->save()) {
                $result = json_encode(['code' => 0]);
            }
        }
        $result = json_encode(['code' => 1]);
        \Log::info('message', $result);
        return $result;
    }

    public function exception(Request $request)
    {
        \Log::info('message', $request);
        $result = [];
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
                $result = json_encode(['code' => 0]);
            }
        }
        $result = json_encode(['code' => 1]);
        \Log::info('message', $result);
        return $result;
    }
}