<?php


namespace App\Libraries\Yaogui\Api;


use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{
    /**
     * 获取订单
     * @param Order $order
     * @return mixed
     */
    public function getOrder(Order $order)
    {
        $data = [
            "orderNo" => $order->order_id
        ];
        return $this->post("order/getByOrderNo", $data);
    }

    /**
     * 取消订单
     * @param Order $order
     * @return mixed
     */
    public function cancelOrder(Order $order)
    {
        $data = [
            "reason" => $order->cancel_reason ?: "无法配送",
            "orderNo" => $order->order_id
        ];
        return $this->post("order/cancel", $data);
    }

    /**
     * 同步配送订单
     */
    public function logisticsOrder($data)
    {
        // $data = [
        //     "courierName" =>  $order->courier_name,
        //     "orderNo" => $order->order_id,
        //     "courierPhone" => $order->courier_phone,
        //     "logisticsStatus" => 1
        // ];
        return $this->post("order/syncLogistics", $data);
    }

    /**
     * 配送中
     * @param Order $order
     * @return mixed
     */
    public function deliveringOrder(Order $order)
    {
        $data = [
            "orderNo" => $order->order_id
        ];
        return $this->post("order/delivering", $data);
    }

    /**
     * 配送完成
     * @param Order $order
     * @return mixed
     */
    public function arrivedOrder(Order $order)
    {
        $data = [
            "orderNo" => $order->order_id
        ];
        return $this->post("order/arrived", $data);
    }
}