<?php


namespace App\Http\Controllers\ShanSong;


use App\Models\Order;
use Illuminate\Http\Request;

class OrderController
{
    public function status(Request $request)
    {
        $res = ['status' => 200, 'msg' => '', 'data' => ''];

        $data = $request->all();

        \Log::info('闪送订单状态回调-全部参数', [$data]);

        if (empty($data)) {
            return $res;
        }

        // 商家订单号
        $ss_order_id = $data['issOrderNo'] ?? '';
        $order_id = $data['orderNo'] ?? '';
        // 状态： 1：订单支付，派单中，2：配送员接单，待取件，3：配送员就位，已到店，4：配送员取货，配送中，5：配送员送件完成，已完成
        $status = $data['status'] ?? '';
        // 配送员姓名
        $name = $data['courier']['name'] ?? '';
        // 配送员手机号
        $phone = $data['courier']['mobile'] ?? '';
        // 配送员经度
        $longitude = $data['courier']['longitude'] ?? '';
        // 配送员纬度
        $latitude = $data['courier']['latitude'] ?? '';

        $order = Order::query()->where('order_id', $order_id)->first();

        if ($order) {
            $order->courier_name = $name;
            $order->courier_phone = $phone;

            // -30 未付款，
            // -20 等待发送，
            // -10 发送失败，
            // 0 订单未发送，
            // 5：余额不足，
            // 10 暂无运力，
            // 20 待接单，
            // 30 平台已接单，
            // 40 已分配骑手，
            // 50 取货中，
            // 60 已取货，
            // 70 已送达，
            // 80 异常，
            // 99 已取消，

            // 20 	派单中 	商户已经支付，订单分配给配送员
            // 30 	取货中 	配送员已经接单
            // 40 	闪送中 	已经取件，配送中
            // 50 	已完成 	已经配送完成
            // 60 	已取消 	已经取消订单

            if ($status == 20) {

                $order->status = 30;

            } elseif ($status == 30) {

                $order->status = 40;

            } elseif ($status == 40) {

                $order->status = 60;

            } elseif ($status == 50) {

                $order->status = 70;

            } elseif ($status == 60) {

                $order->status = 99;

            }

            $order->courier_name = $name ?? '';
            $order->courier_phone = $phone ?? '';

            $order->save();
        }
        
        \Log::info('闪送订单状态回调-部分参数', compact('ss_order_id','order_id', 'status', 'name', 'phone', 'longitude', 'latitude'));


        return $res;
    }
}