<?php


namespace App\Libraries\Shansong\Api;


use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{

    /**
     * 订单创建(门店方式)
     */
    public function createShop(Shop $shop)
    {
        $jwd = gd2bd($shop->shop_lng, $shop->shop_lat);

        $data = [
            "storeName" => $shop->shop_name,
            "cityName" => $shop->city,
            "address" => $shop->shop_address,
            "addressDetail" => "-",
            "latitude" => $jwd['lat'],
            "longitude" => $jwd['lng'],
            "phone" => $shop->contact_phone,
            "goodType" => 1
        ];

        return $this->post('/openapi/merchants/v5/storeOperation', $data);
    }

    /**
     * 获取门店信息
     * @return mixed
     */
    public function getShop()
    {

        return $this->post('/openapi/merchants/v5/queryAllStores', []);
    }

    /**
     * 订单计费
     */
    public function orderCalculate(Shop $shop, Order $order)
    {
        $data = [
            "cityName" => $shop->city,
            "sender" => [
                "fromAddress" => $shop->shop_address,
                "fromAddressDetail" => $shop->shop_address,
                "fromSenderName" => $shop->contact_name,
                "fromMobile" => $shop->contact_phone,
                "fromLatitude" => $shop->shop_lat,
                "fromLongitude" => $shop->shop_lng
            ],
            "receiverList" => [[
                "orderNo" => $order->order_id,
                "toAddress" => $order->receiver_address,
                "toAddressDetail" => $order->receiver_address,
                "toLatitude" => $order->receiver_lat,
                "toLongitude" => $order->receiver_lng,
                "toReceiverName" => $order->receiver_name,
                "toMobile" => $order->receiver_phone,
                "goodType" => 1,
                "weight" => $order->goods_weight,
                "remarks" => $order->note ?? "",
                // "additionFee" => 500,
                // "insurance" => 200,
                // "insuranceProId" => "SS_baofei_001",
                // "orderingSourceType" =>4,
                "orderingSourceNo" => $order->goods_pickup_info ?? ""
            ]],
            "appointType" => $order->order_type ?? "",
            "appointmentDate" => isset($order->expected_pickup_time) ? date("Y-m-d H:i:s", $order->expected_pickup_time) : "",
            "storeId" => $shop->shop_id_ss
        ];

        return $this->post('/openapi/merchants/v5/orderCalculate', $data);
    }

    /**
     * 创建订单
     * @param $order_id
     * @return mixed
     */
    public function createOrder($order_id)
    {
        $data = [
            'issOrderNo' => $order_id
        ];

        return $this->post('/openapi/merchants/v5/orderPlace', $data);
    }

    /**
     * 取消订单
     * @param $order_id
     * @return mixed
     */
    public function cancelOrder($order_id)
    {
        $data = [
            'issOrderNo' => $order_id
        ];

        return $this->post('/openapi/merchants/v5/abortOrder', $data);
    }

    /**
     * 获取订单
     * @param $data
     * @return mixed
     */
    public function getOrder($data)
    {
        return $this->post('/openapi/merchants/v5/orderInfo', $data);
    }

    /**
     * 配送员信息
     * @param $order_id
     * @return mixed
     */
    public function carrier($order_id)
    {
        $data = [
            'issOrderNo' => $order_id
        ];

        return $this->post('/openapi/merchants/v5/courierInfo', $data);
    }
}