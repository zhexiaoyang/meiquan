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
            'delivery_service_code' => 100005,
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

        if ($order->day_seq) {
            $params['poi_seq'] = $order->day_seq;
            if ($order->platform === 1) {
                $params['outer_order_source_desc'] = 101;
                $params['outer_order_source_no'] = $order->order_id;
            }
            if ($order->platform === 2) {
                $params['outer_order_source_desc'] = 102;
                $params['outer_order_source_no'] = $order->order_id;
            }
        }

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
     * 美团预发单
     * @param Shop $shop
     * @param Order $order
     * @return mixed
     * @author zhangzhen
     * @data 2021/8/19 9:05 下午
     */
    public function preCreateByShop(Shop $shop, Order $order)
    {
        $params = [
            'delivery_id' => $order->delivery_id,
            'order_id' => $order->order_id,
            'shop_id' => $shop->shop_id,
            'delivery_service_code' => 100005,
            'receiver_name' => $order->receiver_name,
            'receiver_address' => $order->receiver_address,
            'receiver_phone' => $order->receiver_phone,
            'receiver_lng' => $order->receiver_lng * 1000000,
            'receiver_lat' => $order->receiver_lat * 1000000,
            'coordinate_type' => 0,
            'pay_type_code' => 0,
            'goods_value' => $order->goods_value,
            'goods_weight' => 1,
            'goods_pickup_info' => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : ''
        ];

        if ($order->day_seq) {
            $params['poi_seq'] = $order->day_seq;
            if ($order->platform === 1) {
                $params['outer_order_source_desc'] = 101;
                $params['outer_order_source_no'] = $order->order_id;
            }
            if ($order->platform === 2) {
                $params['outer_order_source_desc'] = 102;
                $params['outer_order_source_no'] = $order->order_id;
            }
        }

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

        return $this->request('order/preCreateByShop', $params);
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
            'delivery_service_code' => 100005,
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

        $second_category = $shop->second_category;

        if ($second_category == '200902' || $second_category == '200903') {
            $second_category = '200001';
        }

        $params = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->shop_name,
            'category' => $shop->category,
            'second_category' => $second_category,
            'contact_name' => (string) $shop->contact_name,
            'contact_phone' => $shop->contact_phone,
            // 'shop_address' => $shop->shop_address . ',' . $shop->shop_name,
            'shop_address' => $shop->shop_address,
            'shop_lng' => ceil($shop->shop_lng * 1000000),
            'shop_lat' => ceil($shop->shop_lat * 1000000),
            'coordinate_type' => 0,
            'delivery_service_codes' => 100005,
            'business_hours' => json_encode($time),
        ];

        return $this->request('shop/create', $params);
    }

    public function shopUpdate(Shop $shop)
    {
        $params = [
            'shop_id' => $shop->shop_id,
            'shop_name' => (string) $shop->shop_name,
            'contact_name' => (string) $shop->contact_name,
            'contact_phone' => $shop->contact_phone,
            'shop_address' => $shop->shop_address . ',' . $shop->shop_name,
            'shop_lng' => ceil($shop->shop_lng * 1000000),
            'shop_lat' => ceil($shop->shop_lat * 1000000),
        ];

        return $this->request('shop/update', $params);
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

    public function syncEstimateArrivalTime($order_id, $date)
    {
        $params = [
            'order_id' => $order_id,
            'estimate_arrival_time' => $date
        ];
        return $this->request_get('v1/ecommerce/order/syncEstimateArrivalTime', $params);
    }

    public function orderConfirm($order_id)
    {
        $params = [
            'order_id' => $order_id,
        ];
        return $this->request_get('v1/ecommerce/order/confirm', $params);
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
     * 同步商家编码
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2020/12/5 7:36 上午
     */
    public function medicineCodeUpdate(array $params)
    {
        return $this->request_post('v1/medicine/code/update', $params);
    }

    /**
     * 批量创建药品
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2021/2/19 7:40 上午
     */
    public function medicineBatchSave(array $params)
    {
        return $this->request_post('v1/medicine/batchsave', $params);
    }
    public function medicineBatchUpdate(array $params)
    {
        return $this->request_post('v1/medicine/batchupdate', $params);
    }

    /**
     * 创建药品分类
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2021/2/19 9:39 上午
     */
    public function medicineCatSave(array $params)
    {
        return $this->request_post('v1/medicineCat/save', $params);
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


    /**
     * 服务商接口
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/10 12:57 下午
     */
    public function waimaiOrderConfirm(array $params)
    {
        return $this->request_get('v1/order/confirm', $params);
    }

    public function waimaiOrderCancel(array $params)
    {
        return $this->request_get('v1/order/cancel', $params);
    }

    public function waimaiOrderRefundAgree(array $params)
    {
        return $this->request_get('v1/order/refund/agree', $params);
    }

    public function waimaiOrderRefundReject(array $params)
    {
        return $this->request_get('v1/order/refund/reject', $params);
    }

    public function waimaiOrderBatchPullPhoneNumber(array $params)
    {
        return $this->request_post('v1/order/batchPullPhoneNumber', $params);
    }

    public function waimaiOrderReviewAfterSales(array $params)
    {
        return $this->request_get('v1/ecommerce/order/reviewAfterSales', $params);
    }

    public function waimaiAuthorize($shop_id)
    {
        $params['app_poi_code'] = $shop_id;
        $params['response_type'] = 'token';
        // $params['version'] = "1.0";
        return $this->request_get('v1/oauth/authorize', $params);
    }

    public function waimaiAuthorizeRef($refresh_token)
    {
        $params['refresh_token'] = $refresh_token;
        $params['grant_type'] = 'refresh_token';
        // $params['version'] = "1.0";
        return $this->request_post('v1/oauth/token', $params);
    }

}
