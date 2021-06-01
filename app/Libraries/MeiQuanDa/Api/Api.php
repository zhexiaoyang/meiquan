<?php

namespace App\Libraries\MeiQuanDa\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{
    /**
     * 创建订单
     */
    public function createOrder(Shop $shop, Order $order)
    {
        $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        $data = [
            // 门店信息
            'shop_id' => $shop->shop_id_mqd,
            'shop_name' => $shop->shop_name,
            'shop_tel' => $shop->contact_phone,
            'shop_address' => $shop->shop_address,
            'shop_tag' => $shop->shop_lng . ',' . $shop->shop_lat,
            // 订单信息
            'customer_name' => $order->receiver_name,
            'customer_tel' => str_replace('_', ',', $order->receiver_phone),
            'customer_address' => $order->receiver_address,
            'customer_tag' => $order->receiver_lng . ',' . $order->receiver_lat,
            // 订单备注
            // 'order_note' => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : 'order_note',
            'order_note' => $order->note ?: "",
            // 'order_mark' => '',
            'order_from' => '',
            // 'order_time' => $order->created_at,
            'order_no' => $order->order_id,
            'pay_status' => 0,
            'is_calc_fee' => 1,
            'weight' => $order->goods_weight
        ];

        if ($order->platform > 0 ) {
            if ($order->platform < 10) {
                $data['order_from'] = empty($platform[$order->platform]) ? "" : "#{$platform[$order->platform]}#" . $order->day_seq;
            } elseif ($order->platform === 11) {
                $data['order_from'] = empty($platform[$order->platform]) ? "" : "#药柜#取货码：" . $order->goods_pickup_info;
            }
        }

        // if (1) {
            // $data['pre_times'] = strtotime("2021-05-22 20:00:00");
        // }

        return $this->post('/open.Order/createOrder', $data);
    }

    public function getOrderInfo($order_id)
    {
        return $this->get('/open.Order/getOrderInfo', ['trade_no' => $order_id]);
    }

    public function repealOrder($order_id)
    {
        return $this->post('/open.Order/repealOrder', ['trade_no' => $order_id, 'reason' => '不需要配送']);
    }
}
