<?php


namespace App\Libraries\Fengniao\Api;


use App\Models\Order;
use App\Models\Shop;

class Api extends Request
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
     * @param Shop $shop
     * @return mixed
     */
    public function createShop(Shop $shop)
    {
        $data = [
            "chain_store_code" => $shop->id,
            "chain_store_name" => $shop->shop_name,
            // 1 正式、2 测试
            "chain_store_type" => 1,
            "merchant_code" => "1587089973709",
            "contact_phone" => $shop->contact_phone,
            "address" => $shop->shop_address,
            "position_source" => 3,
            "longitude" => $shop->shop_lng,
            "latitude" => $shop->shop_lat,
            "service_code" => 1
        ];
        return $this->post('v2/chain_store', $data);
    }

    /**
     * 更新门店
     * @param $data
     * @return mixed
     */
    public function updateShop($data)
    {
        // return $this->post('v2/chain_store/update', $data);
    }

    /**
     * 获取门店信息
     * @param $id
     * @return mixed
     */
    public function getShop($id)
    {
        $data = [
            'chain_store_code' => [(string) $id]
        ];

        return $this->post('v2/chain_store/query', $data);
    }

    /**
     * 获取配送范围
     * @param $id
     * @return mixed
     */
    public function getArea($id)
    {
        $data = [
            'chain_store_code' => (string) $id
        ];

        return $this->post('v2/chain_store/delivery_area', $data);
    }

    /**
     * 创建订单
     * @param Shop $shop
     * @param Order $order
     * @return mixed
     */
    public function createOrder(Shop $shop, Order $order)
    {
        $data = [
            "partner_remark" => "商户备注信息",
            "partner_order_code" => $order->order_id,
            "notify_url" => "http://psapi.meiquanda.com/api/fengniao/order/status",
            "order_type" => $order->order_type ? 3 : 1,
            "chain_store_code" => $shop->shop_id_fn,
            "transport_info" => [
                "transport_name" => $shop->shop_name,
                "transport_address" => $shop->shop_address,
                "transport_longitude" => $shop->shop_lng,
                "transport_latitude" => $shop->shop_lat,
                "position_source" => 3,
                "transport_tel" => $shop->contact_phone,
                "transport_remark" => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : ''
            ],
            "order_add_time" => strtotime($order->created_at) * 1000,
            "order_total_amount" => $order->goods_value,
            "order_actual_amount" => 0,
            "order_weight" => 1,
            "order_remark" => $order->note,
            "is_invoiced" => 0,
            // "invoice" => "xxx有限公司",
            "order_payment_status" => 1,
            "order_payment_method" => 1,
            "is_agent_payment" => 0,
            // "require_payment_pay" => 50.00,
            // 商品数量
            "goods_count" => 4,
            "require_receive_time" => $order->expected_delivery_time * 1000,
            // "serial_number" => $order->goods_pickup_info,
            "receiver_info" => [
                "receiver_name" => $order->receiver_name,
                "receiver_primary_phone" => $order->receiver_phone,
                // "receiver_second_phone" => "13911111111",
                "receiver_address" => $order->receiver_address,
                "receiver_longitude" => $order->receiver_lng,
                "receiver_latitude" => $order->receiver_lat,
                "position_source" => 3
            ],
            "items_json" => [
                [
                    "item_name" => "商品",
                    "item_quantity" => 5,
                    "item_price" => $order->goods_value,
                    "item_actual_price" => $order->goods_value,
                    "is_need_package" => 0,
                    "is_agent_purchase" => 0
                ]
            ],
            // "cooking_time" => 1452570728594,
            // "platform_paid_time" => 1452570728594,
            // "platform_created_time" => 1452570728594,
            // "merchant_code" => "testmerchant"
        ];

        if ($order->goods_pickup_info) {
            $data['serial_number'] = $order->goods_pickup_info;
        }
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

    /**
     * 校验订单
     * @param Shop $shop
     * @param Order $order
     * @return mixed
     */
    public function delivery(Shop $shop, Order $order)
    {
        $data = [
            "chain_store_code" => $shop->shop_id_fn,
            "position_source" => 3,
            "receiver_longitude" => $order->receiver_lng,
            "receiver_latitude" => $order->receiver_lat,
        ];

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
