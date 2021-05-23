<?php

namespace App\Libraries\DaDa\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{
    public function cityCode()
    {
        return $this->post('/api/cityCode/list', []);
    }

    public function createShop(Shop $shop)
    {
        $data = [
            "station_name" => "新门店1",
            "origin_shop_id" => "shop001",
            "area_name" => "浦东新区",
            "station_address" => "地址1",
            "contact_name" => "xxx",
            "city_name" => "上海",
            "business" => 1,
            "lng" => 121.515014,
            "phone" => "13012345678",
            "lat" => 31.229081
        ];
        return $this->post('/api/shop/add', $data);
    }
    /**
     * 创建订单
     */
    public function createOrder(Shop $shop, Order $order)
    {
        $data = [
            // 门店信息
            'shop_id' => $shop->shop_id_mqd,
            'shop_name' => $shop->shop_name,
            'shop_tel' => $shop->contact_phone,
            'shop_address' => $shop->shop_address,
            'shop_tag' => $shop->shop_lng . ',' . $shop->shop_lat,
            // 订单信息
            'customer_name' => $order->receiver_name,
            'customer_tel' => $order->receiver_phone,
            'customer_address' => $order->receiver_address,
            'customer_tag' => $order->receiver_lng . ',' . $order->receiver_lat,
            // 订单备注
            'order_note' => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : 'order_note',
            'order_mark' => 'order_mark',
            'order_from' => 'order_from',
            // 'order_time' => $order->created_at,
            'order_no' => $order->order_id,
            'pay_status' => 0,
            'is_calc_fee' => 1,
        ];

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
