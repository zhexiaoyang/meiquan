<?php

namespace App\Libraries\Ele\Api;

use App\Models\Order;

class Api extends Request
{
    public function shopInfo($shop_id)
    {
        $data = [
            'baidu_shop_id' => $shop_id
        ];

        return $this->post('shop.get', $data);
    }

    /**
     * 订单详情
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/4 8:37 下午
     */
    public function orderInfo($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.get', $data);
    }

    /**
     * 同步状态信息
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/4 8:49 下午
     */
    public function deliveryStatus($order_id)
    {
        $data = [
            'distributor_id' => 201,
            'order_id' => $order_id,
            'state' => 21,
            'knight' => [
                'id' => 1,
                'name' => "张三",
                'phone' => 18210800834
            ]
        ];

        return $this->post('order.selfDeliveryStateSync', $data);
    }
}
