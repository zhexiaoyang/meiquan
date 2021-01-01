<?php


namespace App\Libraries\Meituan\Api;

use App\Models\Order;
use App\Models\Shop;

class Api extends Request
{

    /**
     * 订单创建(门店方式)
     * @param Shop $shop
     * @param Order $order
     * @return mixed
     */
    public function createByShop(Shop $shop, Order $order)
    {
        $params = [
            'delivery_id' => $order->delivery_id,
            'order_id' => $order->order_id,
            'shop_id' => $shop->shop_id,
            'delivery_service_code' => 100004,
            'receiver_name' => $order->receiver_name,
            'receiver_address' => $order->receiver_address,
            'receiver_phone' => $order->receiver_phone,
            'receiver_lng' => $order->receiver_lng * 1000000,
            'receiver_lat' => $order->receiver_lat * 1000000,
            'coordinate_type' => 0,
            'goods_value' => $order->goods_value,
            'goods_weight' => 1,
            'goods_pickup_info' => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : ''
        ];

        if (!empty($order->expected_pickup_time) && ($order->type != 11)) {
            // $params['expected_delivery_time'] = $order->expected_delivery_time;
            $params['expected_pickup_time'] = $order->expected_pickup_time;
        }

        if (!empty($order->note)) {
            $params['goods_delivery_info'] = $order->note ?? "";
        }

        $goods = [];

        if ($items = $order->items) {
            foreach ($items as $item) {
                $_tmp['goodCount'] = $item->quantity;
                $_tmp['goodName'] = $item->name;
                $_tmp['goodPrice'] = $item->goods_price;
                $goods[] = $_tmp;
            }
        }

        \Log::info('美团商品信息',[$goods]);

        if (!empty($goods)) {
            $params['goods_detail'] = json_encode(['goods' => $goods], JSON_UNESCAPED_UNICODE);
        }

        return $this->request('order/createByShop', $params);
    }

    /**
     * 查询订单状态
     *
     * @param array $params
     * @return mixed
     * @throws MeituanDispatchException
     */
    public function queryStatus(array $params)
    {
        return $this->request('order/status/query', $params);
    }

    /**
     * 订单创建(送货分拣方式)
     *
     * @param array $params
     * @return mixed
     * @throws MeituanDispatchException
     */
    public function createByCoordinates(array $params)
    {
        return $this->request('order/createByCoordinates', $params);
    }

    /**
     * 删除订单
     *
     * @param array $params
     * @return mixed
     * @throws MeituanDispatchException
     */
    public function delete(array $params)
    {
        return $this->request('order/delete', $params);
    }

    /**
     * 评价骑手
     *
     * @param array $params
     * @return mixed
     * @throws MeituanDispatchException
     */
    public function evaluate(array $params)
    {
        return $this->request('order/evaluate', $params);
    }

    /**
     * 配送能力校验
     * @param Shop $shop
     * @param Order $order
     * @return mixed
     */
    public function check(Shop $shop, Order $order)
    {
        $params = [
            'shop_id' => (string) $shop->shop_id,
            'delivery_service_code' => 100004,
            'receiver_address' => $order->receiver_address,
            'receiver_lng' => $order->receiver_lng * 1000000,
            'receiver_lat' => $order->receiver_lat * 1000000,
            'coordinate_type' => 0,
            'check_type' => 1,
            'mock_order_time' => time()
        ];

        return $this->request('order/check', $params);
    }

    /**
     * 获取骑手当前位置
     *
     * @param array $params
     * @return mixed
     * @throws MeituanDispatchException
     */
    public function location(array $params)
    {
        return $this->request('order/rider/location', $params);
    }

    /**
     * 获取美团外卖绑定开发者平台的所有美团ID
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function getShopIds()
    {
        return $this->request_get('v1/poi/getids', []);
    }

    /**
     * 获取美团外卖绑定开发者平台的所有美团ID
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function getShopInfoByIds($params)
    {
        return $this->request_get('v1/poi/mget', $params);
    }

    public function shopCreate(Shop $shop)
    {
        $time = [
            [
                "beginTime" => "00:00",
                "endTime" => "23:59"
            ]
        ];

        $params = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->shop_name,
            'category' => $shop->category,
            'second_category' => $shop->second_category,
            'contact_name' => (string) $shop->contact_name,
            'contact_phone' => $shop->contact_phone,
            'shop_address' => $shop->shop_address,
            'shop_lng' => ceil($shop->shop_lng * 1000000),
            'shop_lat' => ceil($shop->shop_lat * 1000000),
            'coordinate_type' => 0,
            'delivery_service_codes' => 100004,
            'business_hours' => json_encode($time),
        ];

        return $this->request('shop/create', $params);
    }

    public function shopInfo(array $params)
    {
        return $this->request('shop/query', $params);
    }


    public function arrange(array $params)
    {
        return $this->request('test/order/arrange', $params);
    }

    public function shopStatus(array $params)
    {
        return $this->request('test/shop/status/callback', $params);
    }

    public function deliver(array $params)
    {
        return $this->request('test/order/deliver', $params);
    }

    public function rearrange(array $params)
    {
        return $this->request('test/order/rearrange', $params);
    }

    public function reportException(array $params)
    {
        return $this->request('test/order/reportException', $params);
    }

    public function getShops(array $params)
    {
        return $this->request_get('v1/poi/mget', $params);
    }

    public function getOrderDetail(array $params)
    {
        return $this->request_get('v1/order/getOrderDetail', $params);
    }

    public function getOrderViewStatus(array $params)
    {
        return $this->request_get('v1/order/viewstatus', $params);
    }

    /**
     * 同步订单状态
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2020/12/5 7:36 上午
     */
    public function logisticsSync(array $params)
    {
        return $this->request_post('v1/ecommerce/order/logistics/sync', $params);
    }

    /**
     * 同步库存
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2020/12/5 7:36 上午
     */
    public function medicineStock(array $params)
    {
        return $this->request_post('v1/medicine/stock', $params);
    }

    /**
     * 获取门店配送范围
     * @param array $params
     * @return mixed
     */
    public function getShopArea(array $params)
    {
        return $this->request('shop/area/query', $params);
    }

}
