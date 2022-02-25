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

    public function shopInfoByStoreId($shop_id)
    {
        $data = [
            'shop_id' => $shop_id
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
     * 确认订单
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function confirmOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.confirm', $data);
    }

    /**
     * 确认订单
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function pickcompleteOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.pickcomplete', $data);
    }

    /**
     * 确认订单
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function completeOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.complete', $data);
    }

    /**
     * 确认订单
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function sendoutOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.sendout', $data);
    }

    /**
     * 同步状态信息
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/4 8:49 下午
     */
    public function deliveryStatus($params)
    {
        $data = [
            'distributor_id' => 201,
            'order_id' => $params['order_id'],
            'state' => 21,
            'knight' => [
                'id' => 1,
                'name' => $params['name'],
                'phone' => $params['phone']
            ]
        ];

        return $this->post('order.selfDeliveryStateSync', $data);
    }
}
