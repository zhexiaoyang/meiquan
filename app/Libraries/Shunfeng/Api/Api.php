<?php

namespace App\Libraries\Shunfeng\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{

    // public function createOrder(Shop $shop, Order $order)
    public function createOrder()
    {
        $time = time();
        $data = [
            "shop_id" => "test_001",
            "shop_type" => 2,
            'shop_order_id' => $time . '2333',
            'order_source' => 1,
            'order_sequence' => 1,
            'pay_type' => 1,
            'order_time' => $time-2*60,
            "is_appoint" => 0,
            "is_insured" => 0,
            "is_person_direct" => 0,
            "return_flag" => 511,
            "receive" => [
                "user_name" => "住这",
                "user_phone" => "18812341233",
                "user_address" => "五楼",
                "user_lng" => 116.338211,
                "user_lat" => 40.031532,
            ],
            "order_detail" => [
                "total_price" => 12100,
                "product_type" => 2,
                "weight_gram" => 3000,
                "product_num" => 4,
                "product_type_num" => 2,
                "product_detail" => [
                    [
                        "product_name" => "bbb",
                        "product_num" => 3
                    ],
                    [
                        "product_name" => "ccc",
                        "product_num" => 1
                    ]
                ]
            ]
        ];
        return $this->post('/open/api/external/createorder', $data);
    }

    public function cancelOrder()
    {
        $data = [
            'order_id' => '16026532192333',
            'order_type' => 2,
            "shop_id" => "test_001",
            "shop_type" => 2,
        ];
        return $this->post('/open/api/external/cancelorder', $data);
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

    public function position()
    {
        $data = [
            'order_id' => '16026532192333',
            'order_type' => 2,
            "shop_id" => "test_001",
            "shop_type" => 2,
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
}
