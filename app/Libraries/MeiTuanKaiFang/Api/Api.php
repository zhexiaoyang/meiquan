<?php

namespace App\Libraries\MeiTuanKaiFang\Api;

use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Models\MeituanOpenToken;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Api extends Request
{
    // public function poi_time()
    // {
    //     $params = [
    //         'openTime' => '00:00-23:59'
    //     ];
    //     $data = [
    //         'appAuthToken' => 'c40c476887b9524d7edeb539e64603b72db91ee73c8e7596e59d52653e82b7c8d5ca936931d1dd3933e9e388b7f7ac8c',
    //         'biz' => json_encode($params)
    //     ];
    //
    //     return $this->post('waimai/poi/updateOpenTime', $data);
    // }
    // public function poi_info($shop_id)
    // {
    //     $params = [
    //         'ePoiIds' => $shop_id
    //     ];
    //     $data = [
    //         'appAuthToken' => '8052f7a7b441f5d2a5ce70b7856e5001662a3a4a4357f938347b76ebef71e79c25b4fa30027c06316cc6c3dc225bd2e6',
    //         'biz' => json_encode($params)
    //     ];
    //
    //     return $this->post('waimai/poi/queryPoiInfo', $data);
    // }
    //
    // public function cat()
    // {
    //     $params = [];
    //
    //     $data = [
    //         'appAuthToken' => '8052f7a7b441f5d2a5ce70b7856e5001662a3a4a4357f938347b76ebef71e79c25b4fa30027c06316cc6c3dc225bd2e6',
    //         'biz' => json_encode($params)
    //     ];
    //
    //     return $this->post('waimai/dish/queryCatList', $data);
    // }

    public function order_confirm($order_id, $shop_id)
    {
        $params = [
            'orderId' => $order_id
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('waimai/order/confirm', $data);
    }

    public function order_cancel($order_id, $shop_id)
    {
        $params = [
            'orderId' => $order_id,
            'reasonCode' => 1204,
            'reason' => '其他原因'
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('waimai/order/cancel', $data);
    }

    public function agree_refund($order_id, $shop_id)
    {
        $params = [
            'orderId' => $order_id,
            'reason' => '同意'
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('waimai/order/agreeRefund', $data);
    }

    public function reject_refund($order_id, $shop_id)
    {
        $params = [
            'orderId' => $order_id,
            'reason' => '拒绝'
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('waimai/order/rejectRefund', $data);
    }

    /**
     * 非接单菜品详情
     * @data 2022/5/1 9:38 下午
     */
    public function wmoper_food_info($food_id, $shop_id)
    {
        $params = [
            'app_food_code' => $food_id
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/food/detail', $data, 16);
    }

    /**
     * 门店菜品列表
     * @param $shop_id
     * @param int $offset
     * @param int $limit
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/21 3:49 下午
     */
    public function wmoper_food_list($shop_id, $offset = 0, $limit = 200)
    {
        $params = [
            'offset' => $offset,
            'limit' => $limit,
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/food/queryFoodList', $data, 16);
    }

    /**
     * 创建或者更新商品
     * @data 2023/12/3 5:19 下午
     */
    public function food_init_data($shop_id, $params)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ];
        return $this->post('wmoper/ng/foodop/food/initdata', $data, 16);
    }

    /**
     * 批量创建商品
     * @data 2023/12/3 5:19 下午
     */
    public function food_batchbulksave($shop_id, $params)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/foodop/food/batchbulksave', $data, 16);
    }

    /**
     * 分类列表
     * @data 2023/12/3 4:15 下午
     */
    public function wmoper_food_category_list($shop_id)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id)
        ];
        return $this->post('wmoper/ng/food/queryFoodCatList', $data, 16);
    }

    /**
     * 创建分类
     * @data 2023/12/3 5:01 下午
     */
    public function food_cat_update($shop_id, $params)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/foodop/foodCat/update', $data, 16);
    }

    /**
     * 根据名称和规格更新店内码
     * @param $shop_id
     * @param $params
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/21 8:29 下午
     */
    public function updateAppFoodCodeByNameAndSpec($shop_id, $params)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ];
        return $this->post('wmoper/ng/foodop/food/updateAppFoodCodeByNameAndSpec', $data, 16);
    }

    /**
     * 非接单订单详情
     * @data 2022/5/1 9:38 下午
     */
    public function wmoper_order_info($order_id, $shop_id)
    {
        $params = [
            'orderId' => $order_id
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/order/queryDetail', $data, 16);
    }

    /**
     * 非接单收货地址详情
     * @data 2022/5/1 9:38 下午
     */
    public function wmoper_order_recipient_info($order_id, $shop_id)
    {
        $params = [
            'orderId' => $order_id
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/delivery/getRecipientInfo', $data, 16);
    }

    /**
     * 非接单同步配送信息
     * @data 2022/5/8 11:11 上午
     */
    public function logistics_sync($params, $shop_id)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/order/riderPosition', $data, 16);
    }

    /**
     * 门店列表获取
     * @data 2022/5/8 11:11 上午
     */
    public function poi_mget($params, $shop_id)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/poi/mget', $data, 16);
    }

    public function ng_shop_ma($shop_id)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
        ];
        return $this->post('wmoper/ng/poi/getPoiExtendInfo', $data, 16);
    }

    public function ng_shop_info($shop_id)
    {
        $data = [
            'epoiIds' => (string) $shop_id,
            'appAuthToken' => $this->get_token($shop_id),
        ];
        return $this->post('wmoper/ng/poi/detail', $data, 16);
    }

    public function uploadDataTransRecord($order_id, $target_id = 100331, $source_id = 106791, $business_id = 16)
    {
        \Log::info("美团订单打印上报:{$order_id}");
        $data = [
            "sourceDeveloperId" => $source_id,
            "targetDeveloperId" => $target_id,
            "businessId" => $business_id,
            "transporterType" => 1,
            "dataType" => 1,
            "data" => $order_id
        ];
        return $this->post2('common/developer/uploadDataTransRecord', $data, 16);
    }

    // ------------------------------------------------------------------------------------------------
    // ---------------------------------------- 美团众包配送 开始 ----------------------------------------
    // ------------------------------------------------------------------------------------------------
    public function zhongbaoFee($ids, $shop_id)
    {
        $data = [
            'orderIds' => (string) $ids,
            'appAuthToken' => $this->get_token($shop_id),
        ];
        return $this->post('wmoper/ng/order/queryZbShippingFee', $data, 16);
    }
    // ------------------------------------------------------------------------------------------------
    // ---------------------------------------- 美团众包配送 结束 ----------------------------------------
    // ------------------------------------------------------------------------------------------------

    public function get_token($shop_id, $order_id = '')
    {
        $key = 'meituan:open:token:' . $shop_id;
        $token = Cache::get($key);
        if (!$token) {
            if ($token_data = MeituanOpenToken::where('shop_id', $shop_id)->first()) {
                $token = $token_data->token;
                // $key = 'meituan:open:token:' . $shop_id;
            } else {
                if ($shop = Shop::select('id', 'waimai_mt')->find($shop_id)) {
                    if ($token_data = MeituanOpenToken::where('shop_id', $shop->waimai_mt)->first()) {
                        $token = $token_data->token;
                        // $key = 'meituan:open:token:' . $shop_id;
                    }
                }
            }
            Cache::put($key, $token);
        }
        if (!$token) {
            $dingding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
            $dingding->sendTextMsg("餐饮服务商token不存在异常,order_id:{$order_id},shop_id:{$shop_id}");
        }
        Log::info("餐饮服务商获取token|order_id:{$order_id},shop_id:{$shop_id},token:{$token}");
        return $token;
    }
}
