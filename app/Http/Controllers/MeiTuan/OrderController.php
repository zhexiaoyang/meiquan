<?php

namespace App\Http\Controllers\MeiTuan;

use App\Jobs\MtLogisticsSync;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;

class OrderController
{
    public function status(Request $request)
    {
        $res = ['code' => 1];
        $status = $request->get('status', '');
        $delivery_id = $request->get('delivery_id', 0);
        $data = $request->only(['courier_name', 'courier_phone', 'cancel_reason_id', 'cancel_reason','status']);
        \Log::info('美团跑腿订单状态回调-全部参数', [$data]);
        if (($order = Order::where('delivery_id', $delivery_id)->first()) && in_array($status, [0, 20, 30, 50, 99])) {

            if ($order->status == 99) {
                \Log::info('美团跑腿订单状态回调-订单已是取消状态', ['order_id' => $order->id, 'shop_id' => $order->shop_id]);
                return json_encode($res);
            }

            $tui = false;
            if ($status == 0) {
                $order->status = 30;

            } elseif ($status == 20) {
                // 已接单
                $order->status = 50;
                $order->receive_at = date("Y-m-d H:i:s");

            } elseif ($status == 30) {
                // 已取货
                $order->status = 60;
                $order->take_at = date("Y-m-d H:i:s");

            } elseif ($status == 50) {
                // 已送达
                $order->status = 70;
                $order->over_at = date("Y-m-d H:i:s");

            } elseif ($status == 99) {
                if ($order->ps != 1) {
                    return json_encode($res);
                }

                if ($order->status < 99) {
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $tui = true;
                }

                $order->status = 99;
            }

            $order->courier_name = $data['courier_name'] ?? '';
            $order->courier_phone = $data['courier_phone'] ?? '';
            $order->cancel_reason_id = $data['cancel_reason_id'] ?? 0;
            $order->cancel_reason = $data['cancel_reason'] ?? '';

            if ($order->save()) {
                $res = ['code' => 0];

                if ($tui) {
                    $shop = Shop::query()->find($order->shop_id);
                    if ($shop) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('美团平台取消订单-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                }
            }
        }
        \Log::info('美团跑腿订单状态回调', ['request' => $request, 'response' => $res]);

        if (in_array($order->status, [40, 50, 60, 70])) {
            dispatch(new MtLogisticsSync($order));
        }

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
