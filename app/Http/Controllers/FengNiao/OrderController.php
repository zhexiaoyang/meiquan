<?php


namespace App\Http\Controllers\FengNiao;


use App\Jobs\MtLogisticsSync;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController
{
    public function status(Request $request)
    {

        \Log::info('蜂鸟订单状态回调-全部参数', [$request->all()]);

        if (!$data_str = $request->get('data', '')) {
            return [];
        }

        $data = json_decode(urldecode($data_str), true);

        if (empty($data)) {
            return [];
        }

        // 商家订单号
        $order_id = $data['partner_order_code'] ?? '';
        // 状态： 1 接单，20 分配骑手，80 骑手到店，2 订单配送中，3 已送达，5 订单异常/拒单
        $status = $data['order_status'] ?? '';
        // 配送员姓名
        $name = $data['carrier_driver_name'] ?? '';
        // 配送员手机号
        $phone = $data['carrier_driver_phone'] ?? '';
        // 错误信息
        $description = $data['description'] ?? '';
        // 错误信息详细
        $detail_description = $data['detail_description'] ?? '';

        $order = Order::where('order_id', $order_id)->first();

        if ($order) {
            $order->courier_name = $name;
            $order->courier_phone = $phone;
            $order->exception_descr = $description;

            if ($status == 1) {
                // 系统已接单
                $order->status = 30;

            } elseif ($status == 20) {
                // 已分配骑手
                $order->status = 40;
                $order->receive_at = date("Y-m-d H:i:s");

            } elseif ($status == 80) {
                // 骑手已到店
                $order->status = 50;

            } elseif ($status == 2) {
                // 配送中
                $order->status = 60;
                $order->take_at = date("Y-m-d H:i:s");

            } elseif ($status == 3) {
                // 已送达
                $order->status = 70;
                $order->over_at = date("Y-m-d H:i:s");

            } elseif ($status == 5) {
                // 系统拒单
                $order->status = 80;

            }

            $order->save();
        }
        
        \Log::info('蜂鸟订单状态回调-部分参数', compact('order_id', 'status', 'name', 'phone', 'description', 'detail_description'));

        if (in_array($order->status, [40, 50, 60, 70])) {
            dispatch(new MtLogisticsSync($order));
        }


        return [];
    }
}