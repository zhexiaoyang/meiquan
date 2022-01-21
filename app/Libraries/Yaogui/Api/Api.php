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

    /**
     * 获取药柜药品信息
     * @param string $no
     * @return mixed
     * @author zhangzhen
     * @data 2022/1/18 12:15 下午
     */
    public function get_stock(string $no)
    {
        return $this->post("terminal/getStock", ['terminalNo' => $no]);
    }

    public function create_order(array $data = [])
    {
        return $this->post("order/create", $data);
    }

    /**
     * 取消订单
     * @param string $order_id
     * @param string $reason
     * @return mixed
     * @author zhangzhen
     * @data 2022/1/18 1:28 下午
     */
    public function cancel_order(string $order_id, string $reason = "无法配送")
    {
        $data = [
            "reason" => $reason,
            "orderNo" => $order_id
        ];
        return $this->post("order/cancel", $data);
    }
}
