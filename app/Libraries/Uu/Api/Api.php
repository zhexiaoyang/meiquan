<?php

namespace App\Libraries\Uu\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{
    public function orderCalculate(Order $order, Shop $shop)
    {
        // $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        $jwd1 = gd2bd($shop->shop_lng, $shop->shop_lat);
        $jwd2 = gd2bd($order->receiver_lng, $order->receiver_lat);
        $data = [
            'origin_id' => $order->order_id,
            'from_address' => $shop->shop_address . ',' . $shop->shop_name,
            'to_address' => $order->receiver_address,
            'city_name' => $shop->city,
            'send_type' => 0,
            'to_lat' => $jwd2['lat'],
            'to_lng' => $jwd2['lng'],
            "from_lat" => $jwd1['lat'],
            "from_lng" => $jwd1['lng'],
        ];

        return $this->post('getorderprice.ashx', $data);
    }
    public function orderCalculateByInfo($order_id, $receiver_address, $receiver_lng, $receiver_lat, Shop $shop)
    {
        // $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        $jwd1 = gd2bd($shop->shop_lng, $shop->shop_lat);
        $jwd2 = gd2bd($receiver_lng, $receiver_lat);
        $data = [
            'origin_id' => $order_id,
            'from_address' => $shop->shop_address . ',' . $shop->shop_name,
            'to_address' => $receiver_address,
            'city_name' => $shop->city,
            'send_type' => 0,
            'to_lat' => $jwd2['lat'],
            'to_lng' => $jwd2['lng'],
            "from_lat" => $jwd1['lat'],
            "from_lng" => $jwd1['lng'],
        ];

        return $this->post('getorderprice.ashx', $data);
    }

    public function city()
    {
        return $this->post('getcitylist.ashx', []);
    }

    public function money()
    {
        return $this->post('getbalancedetail.ashx', []);
    }

    public function addOrder(Order $order, Shop $shop)
    {
        $note = $order->note ?: "";
        $data = [
            'price_token' => $order->price_token,
            'order_price' => (string) $order->money_uu_total,
            'balance_paymoney' => (string) $order->money_uu_need,
            'receiver' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'note' => $note,
            'callback_url' => 'http://psapi.meiquanda.com/api/waimai/uu/order',
            'push_type' => 0,
            // 'push_type' => 2,
            'callme_withtake' => 0,
            'pubusermobile' => $shop->contact_phone,
        ];

        if ($order->platform > 0 ) {
            if ($order->platform < 10) {
                if ($order->platform === 1 || $order->platform === 2) {
                    $data['ordersource'] = $order->platform;
                    $data['shortordernum'] = $order->day_seq;
                }
                // $data['ordersource'] = empty($platform[$order->platform]) ? "" : "#{$platform[$order->platform]}#" . $order->day_seq;
            } elseif ($order->platform === 11) {
                $data['ordersource'] = 3;
                $data['shortordernum'] = "取货码" . $order->goods_pickup_info;
                $data['note'] = "取货码" . $order->goods_pickup_info . ',' . $note;
            }
        }

        return $this->post('addorder.ashx', $data);
    }

    public function addOrderByToken(Order $order, Shop $shop, $token, $money, $total)
    {
        $note = $order->note ?: "";
        $data = [
            'price_token' => $token,
            'order_price' => (string) $total,
            'balance_paymoney' => (string) $money,
            'receiver' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'note' => $note,
            'callback_url' => 'https://psapi.meiquanda.com/api/waimai/uu/order',
            'push_type' => 0,
            // 'push_type' => 2,
            'callme_withtake' => 0,
            'pubusermobile' => $shop->contact_phone,
        ];

        if ($order->platform > 0 ) {
            if ($order->platform < 10) {
                if ($order->platform === 1 || $order->platform === 2) {
                    $data['ordersource'] = $order->platform;
                    $data['shortordernum'] = $order->day_seq;
                }
                // $data['ordersource'] = empty($platform[$order->platform]) ? "" : "#{$platform[$order->platform]}#" . $order->day_seq;
            } elseif ($order->platform === 11) {
                $data['ordersource'] = 3;
                $data['shortordernum'] = "取货码" . $order->goods_pickup_info;
                $data['note'] = "取货码" . $order->goods_pickup_info . ',' . $note;
            }
        }

        return $this->post('addorder.ashx', $data);
    }

    public function cancelOrder(Order $order)
    {
        $data = [
            'origin_id' => $order->order_id,
            'reason' => '顾客更改配送时间'
        ];

        return $this->post('cancelorder.ashx', $data);
    }

    public function cancelOrderByOrderId($order_id)
    {
        $data = [
            'origin_id' => $order_id,
            'reason' => '顾客更改配送时间'
        ];

        return $this->post('cancelorder.ashx', $data);
    }

    public function getOrderInfo($order_id)
    {
        $data = [
            'origin_id' => $order_id,
        ];

        return $this->post('getorderdetail.ashx', $data);
    }

    /**
     * 添加小费
     * @author zhangzhen
     * @data 2023/8/24 2:27 下午
     */
    public function add_tip($order_code, $order_no, $tip)
    {
        $data = [
            'order_code' => $order_code,
            'order_id' => $order_no,
            'onlinefee' => $tip
        ];
        return $this->post('payonlinefee.ashx', $data);
    }
}
