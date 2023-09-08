<?php

namespace App\Libraries\DaDaService\Api;

use App\Libraries\DaDaService\Tool;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopShipper;
use Illuminate\Support\Facades\Cache;

class Api extends Request
{
    public function get_code()
    {
        $params = [
            'appKey' => $this->app_key,
            'nonce' => (string) rand(1111, 9999),
        ];

        return $this->auth_get('/third/party/ticket', $params);
    }

    public function get_url($shop_id, $ticket)
    {
        $params = [
            'appKey' => $this->app_key,
            'nonce' => (string) rand(1111, 9999),
            'shopId' => $shop_id,
            'state' => $shop_id,
            'ticket' => $ticket,
            'resultType' => 1,
            'redirectUrl' => 'https://psapi.meiquanda.com/api/callback/dada/auth'
        ];
        $params['sign'] = Tool::getSignAuth($params, $this->app_secret);

        Cache::add('dadaticket:' . $ticket, $shop_id, 3600);

        return $this->url . '/third/party/oauth?' . http_build_query($params);
    }

    public function get_auth_status($ticket)
    {
        $params = [
            'ticket' => $ticket,
        ];

        return $this->auth_get('/third/party/auth/info', $params);
    }

    public function cityCode()
    {
        return $this->post('/api/order/cancel/reasons', []);
    }

    public function createShop(Shop $shop)
    {
        $data[] = [
            'origin_shop_id' => (string) $shop->id,
            'station_name' => $shop->shop_name,
            'business' => 20,
            'city_name' => $shop->city,
            'area_name' => $shop->area,
            'station_address' => $shop->shop_address . ',' . $shop->shop_name,
            'lng' => (float) $shop->shop_lng,
            'lat' => (float) $shop->shop_lat,
            'contact_name' => $shop->contact_name,
            'phone' => $shop->contact_phone,
        ];

        return $this->post('/api/shop/add', $data);
    }

    public function updateShop(Shop $shop)
    {
        $data = [
            'origin_shop_id' => (string) $shop->id,
            'station_name' => $shop->shop_name,
            'station_address' => $shop->shop_address . ',' . $shop->shop_name,
            'lng' => (float) $shop->shop_lng,
            'lat' => (float) $shop->shop_lat,
            'contact_name' => $shop->contact_name,
            'phone' => $shop->contact_phone,
        ];

        return $this->post('/api/shop/update', $data);
    }

    /**
     * 订单计算，判断是否可以接单
     */
    public function orderCalculate(Shop $shop, Order $order, $tip = 0)
    {
        $shipper = ShopShipper::where('shop_id', $shop->id)->where('platform', 5)->first();
        $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        $data = [
            // 门店信息
            'shop_no' => $shipper->three_id,
            'city_code' => $shop->citycode,
            // 订单信息
            'origin_id' => $order->order_id,
            'cargo_price' => $order->goods_value ?: 200,
            'cargo_weight' => 1,
            'callback' => 'https://psapi.meiquanda.com/api/callback/dada/order',
            'is_prepay' => 0,
            'is_direct_delivery' => 0,
            // 收货信息
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'receiver_address' => $order->receiver_address,
            'receiver_lng' => $order->receiver_lng,
            'receiver_lat' => $order->receiver_lat,
            // 小费（单位：元，精确小数点后一位，小费金额不能高于订单金额。）
            'tips' => $tip,
            // 订单备注
            'order_infonote' => $order->note ?: "",
        ];

        // 订单来源编号，最大长度为30，该字段可以显示在骑士APP订单详情页面，示例：
        // origin_mark_no:"#京东到家#1" // 达达骑士APP看到的是：#京东到家#1
        if ($order->platform > 0 ) {
            if ($order->platform < 10) {
                $data['origin_mark_no'] = empty($platform[$order->platform]) ? "" : "#{$platform[$order->platform]}#" . $order->day_seq;
            } elseif ($order->platform === 11) {
                $data['origin_mark_no'] = empty($platform[$order->platform]) ? "" : "#药柜#取货码：" . $order->goods_pickup_info;
            }
        }

        // 预约发单时间（预约时间unix时间戳(10位),精确到分;整分钟为间隔，并且需要至少提前5分钟预约，可以支持未来3天内的订单发预约单。）
        // $data['delay_publish_time'] = ''

        return $this->post('/api/order/queryDeliverFee', $data);
    }
    public function orderCalculateByInfo($order_id, $receiver_name, $receiver_phone, $receiver_address, $receiver_lng, $receiver_lat, Shop $shop, $cargo_price = 200, $tip = 0)
    {
        $shipper = ShopShipper::where('shop_id', $shop->id)->where('platform', 5)->first();
        $platform = [1 => "美团", 2 => "饿了么", 11 => "药柜"];
        $data = [
            // 门店信息
            'shop_no' => $shipper->three_id,
            'city_code' => $shop->citycode,
            // 订单信息
            'origin_id' => $order_id,
            'cargo_price' => $cargo_price,
            'cargo_weight' => 1,
            'callback' => 'https://psapi.meiquanda.com/api/callback/dada/order',
            'is_prepay' => 0,
            'is_direct_delivery' => 0,
            // 收货信息
            'receiver_name' => $receiver_name,
            'receiver_phone' => $receiver_phone,
            'receiver_address' => $receiver_address,
            'receiver_lng' => $receiver_lng,
            'receiver_lat' => $receiver_lat,
            // 小费（单位：元，精确小数点后一位，小费金额不能高于订单金额。）
            'tips' => $tip,
            // 订单备注
            'order_infonote' => "",
        ];
        return $this->post('/api/order/queryDeliverFee', $data);
    }

    /**
     * 创建订单
     */
    public function createOrder($order_id)
    {
        return $this->post('/api/order/addAfterQuery', ["deliveryNo" => $order_id]);
    }

    /**
     * 取消订单
     */
    public function orderCancel($order_id)
    {
        $data = [
            'order_id' => $order_id,
            'cancel_reason_id' => 4
        ];
        return $this->post('/api/order/formalCancel', $data);
    }

    /**
     * 物品送回
     */
    public function sendBack($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];
        return $this->post('/api/order/confirm/goods', $data);
    }

    public function getUserAccount($shop_id = 0, $category = 1)
    {
        $params = [
            'category' => $category
        ];
        if ($shop_id) {
            $params['shop_no'] = $shop_id;
        }
        return $this->post('/api/balance/query', $params);
    }

    /**
     * 订单信息
     * @data 2022/3/9 9:24 上午
     */
    public function getOrderInfo($order_id)
    {
        return $this->post('/api/order/status/query', ['order_id' => $order_id]);
    }

    /**
     * 门店信息
     * @data 2022/5/26 11:53 下午
     */
    public function getShopInfo($shop_id)
    {
        return $this->post('/api/shop/detail', ['origin_shop_id' => $shop_id]);
    }


    public function getH5Recharge($shop_id, $amount = 1, $category = 'H5')
    {
        return $this->post('/api/recharge', ['shop_no' => $shop_id, 'amount' => $amount, 'category' => $category]);
    }

    /**
     *
     * @author zhangzhen
     * @data 2023/8/24 11:03 上午
     */
    public function add_tip($order__no, $tip)
    {
        // 每次添加的小费将覆盖前一次的小费金额，再次通过该接口添加小费的金额需大于前一次。
        $data = [
            'order_id' => $order__no,
            'tips' => $tip
        ];
        return $this->post('/api/order/addTip', $data);
    }
}
