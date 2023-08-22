<?php


namespace App\Libraries\Meituan\Api;

use App\Models\MeituanShangouToken;
use App\Models\Order;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Traits\NoticeTool;
use Illuminate\Support\Facades\Cache;

class Api extends Request
{
    use NoticeTool;

    public function cancelLogisticsMeiTuan($order_id, $token = false)
    {
        $params = [
            'order_id' => $order_id,
        ];

        return $this->request_get('order/logistics/cancel', $params);
    }

    public function riderLocation($order_id, $mt_peisong_id)
    {
        $params = [
            'delivery_id' => $order_id,
            'mt_peisong_id' => $mt_peisong_id
        ];

        return $this->request('order/rider/location', $params);
    }

    /**
     * 订单创建(门店方式)
     * @param Shop $shop
     * @param Order $order
     * @return mixed
     */
    public function createByShop(Shop $shop, Order $order)
    {
        $delivery_service_code = 100005;
        if (time() >= strtotime('2022-12-01 05:00:00')) {
            $delivery_service_code = 100029;
        }
        $params = [
            'delivery_id' => $order->delivery_id,
            'order_id' => $order->order_id,
            'shop_id' => $shop->shop_id,
            'delivery_service_code' => $delivery_service_code,
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
        $delivery_service_code = 100005;
        if (time() >= strtotime('2022-12-01 05:00:00')) {
            $delivery_service_code = 100029;
        }
        $params = [
            'delivery_id' => $order->delivery_id,
            'order_id' => $order->order_id,
            'shop_id' => $shop->shop_id,
            'delivery_service_code' => $delivery_service_code,
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
        $delivery_service_code = 100005;
        if (time() >= strtotime('2022-12-01 05:00:00')) {
            $delivery_service_code = 100029;
        }
        $params = [
            'shop_id' => (string) $shop->shop_id,
            'delivery_service_code' => $delivery_service_code,
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

    // ----------------------------------------------------------
    // --------------------美 团 外 卖 接 口-----------------------
    // ----------------------------------------------------------
    public function getDeliveryPath($order_id, $app_poi_code)
    {
        $params = [
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
        ];
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($params['app_poi_code']);
        }
        return $this->request_get('v1/order/getDeliveryPath', $params);
    }
    /**
     * 获取众包配送取消原因
     */
    public function getCancelDeliveryReason($order_id, $app_poi_code, $token = false)
    {
        $params = [
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
        ];
        if ($token) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_post('v1/order/getCancelDeliveryReason', $params);
    }
    /**
     * 取消众包配送订单
     */
    public function cancelLogisticsByWmOrderId($order_id, $reason_code, $detail_content, $app_poi_code, $token = false)
    {
        $params = [
            'reason_code' => $reason_code,
            'detail_content' => $detail_content,
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
        ];
        if ($token) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_post('v1/order/cancelLogisticsByWmOrderId', $params);
    }
    /**
     * 批量查询众包配送费
     */
    public function zhongBaoShippingFee($order_id, $app_poi_code = '')
    {
        $params = [
            'order_ids' => $order_id,
        ];
        if ($app_poi_code) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('v1/order/zhongbao/shippingFee', $params);
    }
    /**
     * 众包发配送
     */
    public function zhongBaoDispatch($order_id, $shipping_fee, $app_poi_code = '')
    {
        $params = [
            'order_id' => $order_id,
            'shipping_fee' => $shipping_fee,
            'tip_amount' => 0,
        ];
        if ($app_poi_code) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_post('v1/order/zhongbao/dispatch', $params);
    }
    /**
     * 众包、快送配送单追加小费
     * ------------$tip_amount------------
     * 众包配送小费，单位是元。此字段传入商家追加小费后的合计小费金额，即所传信息会覆盖原信息。
     * 例如，商家发众包配送时已给定小费2元，现在想追加3元，则此字段信息传入5元，即实际此订单发众包配送的小费为5元。
     */
    public function zhongBaoUpdateTip($order_id, $tip_amount, $app_poi_code = '')
    {
        $params = [
            'order_id' => $order_id,
            'tip_amount' => $tip_amount,
        ];
        if ($app_poi_code) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_post('v1/order/zhongbao/update/tip', $params);
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

    public function getShopCats($shop_id)
    {
        $params = [
            'app_poi_code' => $shop_id
        ];
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($params['app_poi_code']);
        }
        return $this->request_get('v1/medicineCat/list', $params);
    }

    public function deleteShopCats($params)
    {
        return $this->request_post('v1/medicineCat/delete', $params);
    }

    public function poiOffline($shop_id)
    {
        $params = ['app_poi_code' => $shop_id];
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($shop_id);
        }
        return $this->request_post('v1/poi/offline', $params);
    }

    public function poiOnline($shop_id)
    {
        return $this->request_post('v1/poi/online', ['app_poi_code' => $shop_id]);
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

    /**
     * 美团外卖-更新营业时间
     * @data 2022/5/7 4:44 下午
     */
    public function shippingTimeUpdate($app_poi_code, $shipping_time, $token)
    {
        $params = [
            'app_poi_code' => $app_poi_code,
            'shipping_time' => $shipping_time
        ];
        if ($token) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('v1/poi/shippingtime/update', $params);
    }

    /**
     * 美团外卖-更新营业时间
     * @data 2022/5/7 4:44 下午
     */
    public function shopOpen($app_poi_code, $token)
    {
        $params = [
            'app_poi_code' => $app_poi_code,
        ];
        if ($token) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('v1/poi/open', $params);
    }
    public function shopClose($app_poi_code, $token)
    {
        $params = [
            'app_poi_code' => $app_poi_code,
        ];
        if ($token) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('v1/poi/close', $params);
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

        $delivery_service_code = 100005;
        if (time() >= strtotime('2022-12-01 05:00:00')) {
            $delivery_service_code = 100029;
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
            'delivery_service_codes' => $delivery_service_code,
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

    public function getOrderDetail(array $params, $app_poi_code = '')
    {
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('v1/order/getOrderDetail', $params);
    }

    public function getOrderViewStatus(array $params)
    {
        return $this->request_get('v1/order/viewstatus', $params);
    }

    /**
     * 美团外卖获取退款记录
     * @data 2022/5/9 9:57 下午
     */
    public function getOrderRefundDetail($order_id, $type = false, $shop_id = '')
    {
        $params = [
            'wm_order_id_view' => $order_id
        ];
        if ($type) {
            // 退款类型：1-全额退款；2-部分退款。如不传此字段代表查询全部类型。
            $params['refund_type'] = $type;
        }

        if ($shop_id) {
            $params['access_token'] = $this->getShopToken($shop_id);
        }
        return $this->request_get('v1/ecommerce/order/getOrderRefundDetail', $params);
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

    public function syncEstimateArrivalTime($order_id, $date, $shop_id = '')
    {
        $params = [
            'order_id' => $order_id,
            'estimate_arrival_time' => $date
        ];

        if ($shop_id) {
            $params['access_token'] = $this->getShopToken($shop_id);
        }
        return $this->request_get('v1/ecommerce/order/syncEstimateArrivalTime', $params);
    }

    public function orderConfirm($order_id, $mt_shop_id = '')
    {
        $params = [
            'order_id' => $order_id,
        ];

        if ($mt_shop_id) {
            $params['access_token'] = $this->getShopToken($mt_shop_id);
        }
        return $this->request_get('v1/order/confirm', $params);
    }

    public function orderPicking($order_id)
    {
        $params = [
            'order_id' => $order_id,
        ];
        return $this->request_get('v1/order/preparationMealComplete', $params);
    }

    /**
     * 获取处方订单图片
     * @author zhangzhen
     * @data 2023/2/21 9:28 上午
     */
    public function rp_picture($order_id, $app_poi_code, $type = 4)
    {
        $params = [
            'order_id' => $order_id,
        ];
        if ($type === 31) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('gw/order/hospital/rp/used/list', $params);
    }

    /**
     * 获取处方订单图片
     * @author zhangzhen
     * @data 2023/2/21 9:28 上午
     */
    public function rp_picture_list($app_poi_code, $order_ids, $type = 4)
    {
        $params = [
            'app_poi_code' => $app_poi_code,
            'order_ids' => $order_ids,
        ];
        if ($type === 31) {
            $params['access_token'] = $this->getShopToken($app_poi_code);
        }
        return $this->request_get('v1/gw/rp/picture/list', $params);
    }

    /**
     * 根据外卖订单获取处方订单图片列表
     * @author zhangzhen
     * @data 2023/2/21 9:28 上午
     */
    public function rp_picture_list_by_order(WmOrder $order)
    {
        // if ($order->from_type !== 4 || $order->from_type !== 31) {
        //     return [];
        // }
        $params = [
            'app_poi_code' => $order->app_poi_code,
            'order_ids' => $order->order_id,
        ];
        if ($order->from_type === 31) {
            $params['access_token'] = $this->getShopToken($order->app_poi_code);
        }
        return $this->request_get('v1/gw/rp/picture/list', $params);
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

    /*
     * 创建药品
     */
    public function medicineSave(array $params)
    {
        return $this->request_post('v1/medicine/save', $params);
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
    public function medicineUpdate(array $params)
    {
        return $this->request_post('v1/medicine/update', $params);
    }
    public function medicineDelete(array $params)
    {
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($params['app_poi_code']);
        }
        return $this->request_post('v1/medicine/delete', $params);
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
     * 闪购零售
     */
    public function retail_list(array $params)
    {
        return $this->request_get('v1/retail/list', $params);
    }
    public function getSpTagIds(array $params = [])
    {
        return $this->request_get('v1/retail/getSpTagIds', $params);
    }
    public function getCategoryAttrList($id)
    {
        return $this->request_get('v1/gw/category/attr/list', ['tag_id' => $id]);
    }
    // 根据商品名称和规格名称更换新的商品编码 200005793
    public function updateAppFoodCodeByNameAndSpec(array $params)
    {
        return $this->request_post('v1/retail/updateAppFoodCodeByNameAndSpec', $params);
    }


    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    // -------------------------------------- 美团外卖-药品接口-开始 --------------------------------------
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    /**
     * 获取药品列表
     * $params参数
     * app_poi_code
     * offset 分页查询偏移量，表示从第几页开始查询，需根据公式得到：页码(舍弃小数)=offset/limit+1。
     * limit 分页每页展示药品数量，须为大于0的整数，最多支持200。
     */
    public function medicineList(array $params, bool $token = false)
    {
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($params['app_poi_code']);
        }
        return $this->request_get('v1/medicine/list', $params);
    }
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    // -------------------------------------- 美团外卖-药品接口-结束 --------------------------------------
    // ------------------------------------------------------------------------------------------------






    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    // -------------------------------------- 美团外卖-闪购接口-开始 --------------------------------------
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------

    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    // -------------------------------------- 美团外卖-闪购接口-结束 --------------------------------------
    // ------------------------------------------------------------------------------------------------






    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------ 美团服务商接口（餐饮）-开始 ------------------------------------
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
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
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------ 美团服务商接口（餐饮）-结束 ------------------------------------
    // ------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------

    /**
     * 闪购-授权
     */
    public function waimaiAuthorize($shop_id)
    {
        $params['app_poi_code'] = $shop_id;
        $params['response_type'] = 'token';
        // $params['version'] = "1.0";
        return $this->request_get('v1/oauth/authorize', $params);
    }

    /**
     * 闪购-刷新Token
     */
    public function waimaiAuthorizeRef($refresh_token)
    {
        $params['refresh_token'] = $refresh_token;
        $params['grant_type'] = 'refresh_token';
        // $params['version'] = "1.0";
        return $this->request_post('v1/oauth/token', $params);
    }

    public function getShopToken($shop_id)
    {
        $key = 'mtwm:shop:auth:' . $shop_id;
        $access_token = Cache::store('redis')->get($key, '');
        if (!$access_token) {
            $key_ref = 'mtwm:shop:auth:ref:' . $shop_id;
            $refresh_token = Cache::store('redis')->get($key_ref);
            $token_res = MeituanShangouToken::where('shop_id', $shop_id)->first();
            if (!$refresh_token) {
                if ($token_res) {
                    $refresh_token = $token_res->refresh_token;
                }
            }
            if (!$refresh_token) {
                // $this->ding_error("闪购门店刷新token不存在错误，shop_id:{$shop_id}");
                \Log::info("闪购门店刷新token不存在错误，shop_id:{$shop_id}");
                return false;
            }
            $res = $this->waimaiAuthorizeRef($refresh_token);
            if (!empty($res['access_token'])) {
                $access_token = $res['access_token'];
                $refresh_token = $res['refresh_token'];
                $expires_in = $res['expires_in'];
                Cache::put($key, $access_token, $expires_in - 100);
                Cache::put($key_ref, $refresh_token);
                Cache::forever($key_ref, $refresh_token);
                if ($token_res) {
                    MeituanShangouToken::where('shop_id', $shop_id)->update([
                        'access_token' => $res['access_token'],
                        'refresh_token' => $res['refresh_token'],
                        'expires_at' => date("Y-m-d H:i:s", time() + $expires_in),
                        'expires_in' => $expires_in,
                    ]);
                } else {
                    MeituanShangouToken::create([
                        'shop_id' => $shop_id,
                        'access_token' => $res['access_token'],
                        'refresh_token' => $res['refresh_token'],
                        'expires_at' => date("Y-m-d H:i:s", time() + $expires_in),
                        'expires_in' => $expires_in,
                    ]);
                }
                $this->ding_exception("重新获取Token成功，shop_id:{$shop_id}");
            } else {
                $this->ding_exception("闪购门店刷新token获取token失败错误，shop_id:{$shop_id}");
                \Log::info("闪购门店刷新token获取token失败错误", [$res]);
                return false;
            }
        }

        return $access_token;
    }

    /**
     * 零售类接口
     */
    public function retailCatList($params)
    {
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($params['app_poi_code']);
        }
        return $this->request_get('v1/retailCat/list', $params);
    }
    public function retailCatUpdate($params)
    {
        return $this->request_post('v1/retailCat/update', $params);
    }
    public function retailList($params)
    {
        if ($this->appKey == 6167) {
            $params['access_token'] = $this->getShopToken($params['app_poi_code']);
        }
        return $this->request_get('v1/retail/list', $params);
    }
    public function retailGet($params)
    {
        return $this->request_get('v1/retail/get', $params);
    }
    public function retailBatchInitData($params)
    {
        return $this->request_post('v1/retail/batchinitdata', $params);
    }
    public function retailInitData($params)
    {
        return $this->request_post('v1/retail/initdata', $params);
    }
    public function retailDelete($params)
    {
        return $this->request_post('v1/retail/delete', $params);
    }
    public function shangouDeleteAll($params)
    {
        return $this->request_post('v1/retailCat/batchdelete/catandretail', $params);
    }
    public function retailSkuStock($params)
    {
        return $this->request_post('v1/retail/sku/stock', $params);
    }
    public function retailSkuSave($params)
    {
        return $this->request_post('v1/retail/sku/save', $params);
    }

    // ------------------------------------------------------------------------------------------------
    // ---------------------------------------- 美团众包配送 开始 ----------------------------------------
    // ------------------------------------------------------------------------------------------------
    public function zhongbaoFee($params)
    {
        return $this->request_get('v1/order/zhongbao/shippingFee', $params);
    }
    public function zhongbaoFeePreview($order_id)
    {
        $data = [
            'order_id' => (string) $order_id,
        ];
        return $this->request_get('order/logistics/pt/preview', $data);
    }
    public function zhongbaoFeeDispatch($order_id, $money, $tip = 0)
    {
        $data = [
            'order_id' => (string) $order_id,
            'shipping_fee' => (double) $money,
            'tip_amount' => (double) $tip,
        ];
        return $this->request_get('order/zhongbao/dispatch', $data);
    }
    // ------------------------------------------------------------------------------------------------
    // ---------------------------------------- 美团众包配送 结束 ----------------------------------------
    // ------------------------------------------------------------------------------------------------

    /**
     * @param $user_id
     * @param int $type (1 民康，2 闪购)
     * @param string $auth
     * @return string
     * @author zhangzhen
     * @data 2022/8/9 5:55 下午
     */
    public function webim_index($user_id, $type = 2, $auth = '21')
    {
        $this->url = 'https://open-shangou.meituan.com/';
        $action = 'webim/api/index';

        if ($type === 1) {
            $action = 'webim/api/singlepoi/chat';
        }

        $params = [
            'app_id' => $this->appKey,
            'timestamp' => time(),
            'accountId' => (string) $user_id,
            // 'authScope' => $auth,
            // 'appPoiCode' => '13676234',
        ];

        if ($type === 1) {
            $params['appPoiCode'] = $auth;
        } else {
            $params['authScope'] = $auth;
        }

        $sig = $this->generate_signature($action, $params);

        $url = $this->url . $action . "?sig=".$sig."&".$this->concatParams($params);

        return $url;
    }
}
