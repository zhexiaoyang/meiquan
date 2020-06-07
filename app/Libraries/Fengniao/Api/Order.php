<?php


namespace App\Libraries\Fengniao\Api;


class Order extends Api
{

    /**
     * 获取token
     */
    public function generateSign()
    {
        return $this->get('get_access_token', []);
    }

    /**
     * 订单创建(门店方式)
     */
    public function createShop($data)
    {
        return $this->post('v2/chain_store', $data);
    }

    /**
     * 更新门店
     * @param $data
     * @return mixed
     */
    public function updateShop($data)
    {
        return $this->post('v2/chain_store/update', $data);
    }

    /**
     * 获取门店信息
     * @param $ids
     * @return mixed
     */
    public function getShop($ids)
    {
        $data = [
            'chain_store_code' => $ids
        ];

        return $this->post('v2/chain_store/query', $data);
    }

    /**
     * 获取配送范围
     * @param $data
     * @return mixed
     */
    public function getArea($data)
    {
        return $this->post('v2/chain_store/delivery_area', $data);
    }

    /**
     * 创建订单
     * @param $data
     * @return mixed
     */
    public function createOrder($data)
    {
        return $this->post('v2/order', $data);
    }

    /**
     * 取消订单
     * @param $data
     * @return mixed
     */
    public function cancelOrder($data)
    {
        return $this->post('v2/order/cancel', $data);
    }

    /**
     * 获取订单
     * @param $order_id
     * @return mixed
     */
    public function getOrder($order_id)
    {
        $data = [
            "partner_order_code" => $order_id
        ];
        return $this->post('v2/order/query', $data);
    }

    /**
     * 投诉订单
     * @param $data
     * @return mixed
     */
    public function complaintOrder($data)
    {
        return $this->post('v2/order/complaint', $data);
    }

    public function delivery($data)
    {
        return $this->post('v2/chain_store/delivery/query', $data);
    }

    public function carrier($data)
    {
        return $this->post('v2/order/carrier', $data);
    }

    public function route($data)
    {
        return $this->post('v2/order/carrier_route', $data);
    }
}