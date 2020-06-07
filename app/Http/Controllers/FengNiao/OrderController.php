<?php


namespace App\Http\Controllers\FengNiao;


use Illuminate\Http\Request;

class OrderController
{
    public function status(Request $request)
    {

        \Log::info('蜂鸟订单状态回调Request', [$request->all()]);

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
        
        \Log::info('蜂鸟订单状态回调', compact('order_id', 'status', 'name', 'phone', 'description', 'detail_description'));


        return [];
    }
}