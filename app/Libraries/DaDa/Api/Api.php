<?php

namespace App\Libraries\DaDa\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{
    public function cityCode()
    {
        return $this->post('/api/order/cancel/reasons', []);
    }

    public function createShop(Shop $shop)
    {
        $data[] = [
            'origin_shop_id' => (string) $shop->id,
            'station_name' => $shop->shop_name,
            'business' => 20,
            'city_name' => $shop->city,
            'area_name' => $shop->area,
            'station_address' => $shop->shop_address . ',' . $shop->shop_name,
            'lng' => (float) $shop->shop_lng,
            'lat' => (float) $shop->shop_lat,
            'contact_name' => $shop->contact_name,
            'phone' => $shop->contact_phone,
        ];

        return $this->post('/api/shop/add', $data);
    }

    public function updateShop(Shop $shop)
    {
        $data = [
            'origin_shop_id' => (string) $shop->id,
            'station_name' => $shop->shop_name,
            'station_address' => $shop->shop_address . ',' . $shop->shop_name,
            'lng' => (float) $shop->shop_lng,
            'lat' => (float) $shop->shop_lat,
            'contact_name' => $shop->contact_name,
            'phone' => $shop->contact_phone,
        ];

        return $this->post('/api/shop/update', $data);
    }

    /**
     * 订单计算，判断是否可以接单
     */
    public function orderCalculate(Shop $shop, Order $order)
    {
        $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        $data = [
            // 门店信息
            'shop_no' => $shop->shop_id_dd,
            'city_code' => $shop->citycode,
            // 订单信息
            'origin_id' => $order->order_id,
            'cargo_price' => $order->goods_value,
            'cargo_weight' => 1,
            'callback' => 'http://psapi.meiquanda.com/api/waimai/dada/order',
            'is_prepay' => 0,
            'is_direct_delivery' => 0,
            // 收货信息
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'receiver_address' => $order->receiver_address,
            'receiver_lng' => $order->receiver_lng,
            'receiver_lat' => $order->receiver_lat,
            // 订单备注
            'order_infonote' => $order->note ?: "",
        ];

        // 订单来源编号，最大长度为30，该字段可以显示在骑士APP订单详情页面，示例：
        // origin_mark_no:"#京东到家#1" // 达达骑士APP看到的是：#京东到家#1
        if ($order->platform > 0 ) {
            if ($order->platform < 10) {
                $data['origin_mark_no'] = empty($platform[$order->platform]) ? "" : "#{$platform[$order->platform]}#" . $order->day_seq;
            } elseif ($order->platform === 11) {
                $data['origin_mark_no'] = empty($platform[$order->platform]) ? "" : "#药柜#取货码：" . $order->goods_pickup_info;
            }
        }

        // 预约发单时间（预约时间unix时间戳(10位),精确到分;整分钟为间隔，并且需要至少提前5分钟预约，可以支持未来3天内的订单发预约单。）
        // $data['delay_publish_time'] = ''

        return $this->post('/api/order/queryDeliverFee', $data);
    }

    /**
     * 创建订单
     */
    public function createOrder($order_id)
    {
        return $this->post('/api/order/addAfterQuery', ["deliveryNo" => $order_id]);
    }

    /**
     * 取消订单
     */
    public function orderCancel($order_id)
    {
        $data = [
            'order_id' => $order_id,
            'cancel_reason_id' => 4
        ];
        return $this->post('/api/order/formalCancel', $data);
    }

    /**
     * 物品送回
     */
    public function sendBack($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];
        return $this->post('/api/order/confirm/goods', $data);
    }

    public function getUserAccount()
    {
        return $this->post('/api/balance/query', ['category' => 1]);
    }
}
