<?php


namespace App\Libraries\Shansong\Api;


use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{

    /**
     * 创建门店
     */
    public function createShop(Shop $shop)
    {
        $jwd = gd2bd($shop->shop_lng, $shop->shop_lat);

        $data = [
            "storeName" => $shop->shop_name,
            "cityName" => $shop->city,
            "address" => $shop->shop_address . ',' . $shop->shop_name,
            "addressDetail" => "-",
            "latitude" => $jwd['lat'],
            "longitude" => $jwd['lng'],
            "phone" => $shop->contact_phone,
            "goodType" => 13
        ];

        return $this->post('/openapi/merchants/v5/storeOperation', $data);
    }

    /**
     * 更新门店
     */
    public function updateShop(Shop $shop)
    {
        $jwd = gd2bd($shop->shop_lng, $shop->shop_lat);

        $data = [
            "storeId" => $shop->shop_id_ss,
            "storeName" => $shop->shop_name,
            "cityName" => $shop->city,
            "address" => $shop->shop_address . ',' . $shop->shop_name,
            "addressDetail" => "-",
            "latitude" => $jwd['lat'],
            "longitude" => $jwd['lng'],
            "phone" => $shop->contact_phone,
            "goodType" => 13,
            "operationType" => 2
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
    public function orderCalculate(Shop $shop, Order $order, $tip = 0)
    {

        // $jwd1 = gd2bd($shop->shop_lng, $shop->shop_lat);
        // $jwd2 = gd2bd($order->receiver_lng, $order->receiver_lat);
        $caution = preg_replace('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, '');
        $data = [
            "cityName" => $shop->city,
            "lbsType" => 1,
            "sender" => [
                "fromAddress" => $shop->shop_address,
                "fromAddressDetail" => $shop->shop_name ?? "",
                "fromSenderName" => $shop->contact_name,
                "fromMobile" => $shop->contact_phone,
                // "fromLatitude" => $jwd1['lat'],
                // "fromLongitude" => $jwd1['lng'],
                "fromLatitude" => $shop->shop_lat,
                "fromLongitude" => $shop->shop_lng,
            ],
            "receiverList" => [[
                "orderNo" => $order->order_id,
                "toAddress" => $order->receiver_address,
                "toAddressDetail" => $order->receiver_address,
                // "toLatitude" => $jwd2['lat'],
                // "toLongitude" => $jwd2['lng'],
                "toLatitude" => $order->receiver_lat,
                "toLongitude" => $order->receiver_lng,
                "toReceiverName" => $order->receiver_name ?: "无名",
                "toMobile" => str_replace('_', '#', $order->receiver_phone),
                "goodType" => 13,
                "weight" => 1,
                "remarks" => $caution . !empty($order->note) ? '，'.$order->note : '',
                // 小费，单位 分
                "additionFee" => (int) $tip * 100,
                // "additionFee" => 500,
                // "insurance" => 200,
                // "insuranceProId" => "SS_baofei_001",
                // "orderingSourceType" =>4,
                // "orderingSourceNo" => $order->goods_pickup_info ?? "",
                // "orderingSourceNo" => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : ''
            ]],
            // "appointType" => $order->order_type ?? "",
            "appointType" => 0,
            // "appointmentDate" => (isset($order->expected_pickup_time) && $order->expected_pickup_time) ? date("Y-m-d H:i", $order->expected_pickup_time) : "",
            // "appointmentDate" => "",
            "travelWay" => $order->tool === 8 ? 8 : 0,
            "storeId" => $shop->shop_id_ss
        ];


        if ($order->goods_pickup_info) {
            $data['receiverList'][0]['orderingSourceType'] = 4;
            $data['receiverList'][0]['orderingSourceNo'] = "取货码：" . $order->goods_pickup_info;
        } elseif ($order->day_seq) {
            if ($order->platform === 1) {
                $data['receiverList'][0]['orderingSourceType'] = 4;
                $data['receiverList'][0]['orderingSourceNo'] = $order->day_seq;
            }
            if ($order->platform === 2) {
                $data['receiverList'][0]['orderingSourceType'] = 3;
                $data['receiverList'][0]['orderingSourceNo'] = $order->day_seq;
            }
        }

        // if ($order->type === 11) {
        //     $data['appointmentDate'] = '';
        // }

        return $this->post('/openapi/merchants/v5/orderCalculate', $data);
    }
    public function orderCalculateByInfo($order_id, $receiver_name, $receiver_phone, $receiver_address, $receiver_lng, $receiver_lat, Shop $shop, $tip = 0)
    {
        $data = [
            "cityName" => $shop->city,
            "lbsType" => 1,
            "sender" => [
                "fromAddress" => $shop->shop_address,
                "fromAddressDetail" => $shop->shop_name ?? "",
                "fromSenderName" => $shop->contact_name,
                "fromMobile" => $shop->contact_phone,
                "fromLatitude" => $shop->shop_lat,
                "fromLongitude" => $shop->shop_lng,
            ],
            "receiverList" => [[
                "orderNo" => $order_id,
                "toAddress" => $receiver_address,
                "toAddressDetail" => $receiver_address,
                "toLatitude" => $receiver_lat,
                "toLongitude" => $receiver_lng,
                "toReceiverName" => $receiver_name,
                "toMobile" => str_replace('_', '#', $receiver_phone),
                "goodType" => 13,
                "weight" => 1,
                "remarks" => '',
                // 小费，单位 分
                "additionFee" => (int) $tip * 100,
            ]],
            "appointType" => 0,
            "travelWay" => 0,
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
    public function createOrderByOrder(Order $order)
    {
        $data = [
            'issOrderNo' => $order->ss_order_id
        ];

        return $this->post('/openapi/merchants/v5/orderPlace', $data);
    }
    public function createOrderByOrderNo($ss_order_id)
    {
        $data = [
            'issOrderNo' => $ss_order_id
        ];

        return $this->post('/openapi/merchants/v5/orderPlace', $data);
    }

    /**
     * 取消订单
     * @param $order_id
     * @return mixed
     */
    public function cancelOrder($order_id, $shop_id = '')
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

    /**
     * 确认物品送回
     * @param $order_id
     * @return mixed
     */
    public function confirmGoodsReturn($order_id)
    {
        $data = [
            'issOrderNo' => $order_id
        ];

        return $this->post('/openapi/merchants/v5/confirmGoodsReturn', $data);
    }

    public function getUserAccount()
    {
        return $this->post('/openapi/merchants/v5/getUserAccount', []);
    }

    /**
     * 加小费
     * @author zhangzhen
     * @data 2023/8/24 11:02 上午
     */
    public function add_tip($three_no, $tip)
    {
        $data = [
            'issOrderNo' => $three_no,
            'additionAmount' => $tip * 100
        ];
        return $this->post('/openapi/developer/v5/addition', $data);
    }
}
