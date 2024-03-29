<?php

namespace App\Libraries\Shunfeng\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{
    public $product_data = [
        "110" => 1,
        "120" => 3,
        "150" => 6,
        "160" => 3,
        "180" => 14,
        "200" => 2,
        "210" => 99,
        "240" => 3,
        "270" => 34,
        "330" => 99,
    ];

    public function precreateorder(Order $order, Shop $shop, $tip = 0)
    {
        $tip = (int) $tip * 100;
        if ($tip < 100) {
            $tip = 0;
        }
        // $shop = Shop::query()->find($order->shop_id);
        $time = time();
        $shop_info = [
            "shop_name" => $shop->shop_name,
            "shop_phone" => $shop->contact_phone,
            "shop_address" => $shop->shop_address . ',' . $shop->shop_name,
            "shop_lng" => $shop->shop_lng,
            "shop_lat" => $shop->shop_lat,
        ];
        $data = [
            "shop_id" => (string) intval($shop->citycode),
            "shop_type" => 2,
            "user_lng" => (string) $order->receiver_lng,
            "user_lat" => (string) $order->receiver_lat,
            "user_address" => $order->receiver_address,
            "city_name" => $shop->city,
            "weight" => 1000,
            // "product_type" => isset($this->product_data[$shop->category]) ? $this->product_data[$shop->category] : 99,
            "product_type" => 2,
            // 是否是预约单	0：非预约单；1：预约单
            "is_appoint" => 0,
            // 单位分，加小费最低不能少于100分
            "gratuity_fee" => $tip,
            // "appoint_type"
            // "expect_time"
            "lbs_type" => 2,
            "pay_type" => 1,
            "is_insured" => 0,
            "is_person_direct" => 0,
            "return_flag" => 511,
            "push_time" => $time,
            "shop" => $shop_info
        ];
        return $this->post('/open/api/external/precreateorder', $data);
    }

    public function precreateorderByInfo($receiver_lng, $receiver_lat, $receiver_address, Shop $shop, $tip = 0)
    {
        $tip = (int) $tip * 100;
        if ($tip < 100) {
            $tip = 0;
        }
        // $shop = Shop::query()->find($order->shop_id);
        $time = time();
        $shop_info = [
            "shop_name" => $shop->shop_name,
            "shop_phone" => $shop->contact_phone,
            "shop_address" => $shop->shop_address . ',' . $shop->shop_name,
            "shop_lng" => $shop->shop_lng,
            "shop_lat" => $shop->shop_lat,
        ];
        $data = [
            "shop_id" => (string) intval($shop->citycode),
            "shop_type" => 2,
            "user_lng" => $receiver_lng,
            "user_lat" => $receiver_lat,
            "user_address" => $receiver_address,
            "city_name" => $shop->city,
            "weight" => 1000,
            "product_type" => isset($this->product_data[$shop->category]) ? $this->product_data[$shop->category] : 99,
            // 是否是预约单	0：非预约单；1：预约单
            "is_appoint" => 0,
            // 单位分，加小费最低不能少于100分
            "gratuity_fee" => $tip,
            // "appoint_type"
            // "expect_time"
            "lbs_type" => 2,
            "pay_type" => 1,
            "is_insured" => 0,
            "is_person_direct" => 0,
            "return_flag" => 511,
            "push_time" => $time,
            "shop" => $shop_info
        ];
        return $this->post('/open/api/external/precreateorder', $data);
    }

    public function createOrder(Order $order, Shop $shop, $tip = 0)
    {
        $tip = (int) $tip * 100;
        if ($tip < 100) {
            $tip = 0;
        }
        $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        // $shop = Shop::query()->find($order->shop_id);
        $time = time();
        $shop_info = [
            "shop_name" => $shop->shop_name,
            "shop_phone" => $shop->contact_phone,
            "shop_address" => $shop->shop_address . ',' . $shop->shop_name,
            "shop_lng" => $shop->shop_lng,
            "shop_lat" => $shop->shop_lat,
        ];
        $data = [
            "shop_id" => (string) intval($shop->citycode),
            "shop_type" => 2,
            'shop_order_id' => $order->delivery_id,
            // 'order_source' => 1,
            // 'order_sequence' => 1,
            "lbs_type" => 2,
            'pay_type' => 1,
            'order_time' => strtotime($order->created_at),
            "is_appoint" => 0,
            // 单位分，加小费最低不能少于100分
            "gratuity_fee" => $tip,
            "is_insured" => 0,
            "is_person_direct" => 0,
            // "remark" => "",
            "return_flag" => 511,
            "push_time" => $time,
            "version" => 19,
            "receive" => [
                "user_name" => $order->receiver_name,
                "user_phone" => $order->receiver_phone,
                "user_address" => $order->receiver_address,
                "user_lng" => (string) $order->receiver_lng,
                "user_lat" => (string) $order->receiver_lat,
                "city_name" => $shop->city,
            ],
            "shop" => $shop_info,
            "order_detail" => [
                "total_price" => $order->goods_value * 100,
                "product_type" => isset($this->product_data[$shop->category]) ? $this->product_data[$shop->category] : 99,
                "weight_gram" => 1000,
                "product_num" => 1,
                "product_type_num" => 1,
                "product_detail" => [
                    [
                        "product_name" => "商品1",
                        "product_num" => 1
                    ]
                ]
            ]
        ];
        if ($order->platform > 0 ) {
            if ($order->platform < 10) {
                $data['ordersource'] = $order->platform;
                $data['order_sequence'] = $order->day_seq;
            } elseif ($order->platform === 11) {
                $data['order_sequence'] = $order->day_seq;
                $data['order_source'] = "药柜#取货码：" . $order->goods_pickup_info;
            }
        }
        return $this->post('/open/api/external/createorder', $data);
    }

    public function cancelOrder(Order $order)
    {
        $shop = Shop::find($order->shop_id);
        $data = [
            'order_id' => $order->delivery_id,
            'order_type' => 2,
            "shop_id" => (string) intval($shop->citycode),
            "shop_type" => 2,
        ];
        return $this->post('/open/api/external/cancelorder', $data);
    }

    public function cancelOrderByOrderId($delivery_id, $shop_id)
    {
        $shop = Shop::find($shop_id);
        $data = [
            'order_id' => $delivery_id,
            'order_type' => 2,
            "shop_id" => (string) intval($shop->citycode),
            "shop_type" => 2,
        ];
        return $this->post('/open/api/external/cancelorder', $data);
    }

    public function notifyproductready(Order $order)
    {
        $shop = Shop::find($order->shop_id);
        $data = [
            'order_id' => $order->delivery_id,
            'order_type' => 2,
            "shop_id" => (string) intval($shop->citycode),
            "shop_type" => 2,
            "notice_ready_time" => time()
        ];
        return $this->post('/open/api/external/notifyproductready', $data);
    }

    public function getshopaccountbalance()
    {
        $data = [
            "shop_id" => "432",
            "shop_type" => 2,
        ];
        return $this->post('/open/api/external/getshopaccountbalance', $data);
    }

    public function orderStatus()
    {
        $data = [
            'order_id' => '16026532192333',
            'order_type' => 2,
            "shop_id" => "test_001",
            "shop_type" => 2,
        ];
        return $this->post('/open/api/external/listorderfeed', $data);
    }

    public function position($order_id)
    {
        $data = [
            'order_id' => $order_id,
            'order_type' => 1,
            // "shop_id" => "test_001",
            // "shop_type" => 2,
        ];
        return $this->post('/open/api/external/riderlatestposition', $data);
    }

    public function h5()
    {
        $data = [
            'order_id' => '16026532192333',
            'order_type' => 2,
            "shop_id" => "test_001",
            "shop_type" => 2,
        ];
        return $this->post('/open/api/external/riderviewv2', $data);
    }

    /**
     * 添加小费
     * @author zhangzhen
     * @data 2023/8/24 2:27 下午
     */
    public function add_tip($tip, $order_id, $shop_id, $order_type = 2, $shop_type = 2)
    {
        $data = [
            'gratuity_fee' => $tip * 100,
            'order_id' => $order_id,
            'order_type' => $order_type,
            "shop_id" => (string) $shop_id,
            "shop_type" => $shop_type,
        ];;
        return $this->post('/open/api/external/addordergratuityfee', $data);
    }
    public function get_tip($order_id, $shop_id, $order_type = 2, $shop_type = 2)
    {
        $data = [
            'order_id' => $order_id,
            'order_type' => $order_type,
            "shop_id" => (string) $shop_id,
            "shop_type" => $shop_type,
        ];
        return $this->post('/open/api/external/getordergratuityfee', $data);
    }
}
