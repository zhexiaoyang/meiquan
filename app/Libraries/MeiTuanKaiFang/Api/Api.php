<?php

namespace App\Libraries\MeiTuanKaiFang\Api;

use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Models\MeituanOpenToken;
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
    public function wmoper_food_list($shop_id)
    {
        $params = [
            'offset' => 2,
            'limit' => 100,
        ];
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
            'biz' => json_encode($params)
        ];
        return $this->post('wmoper/ng/food/queryFoodList', $data, 16);
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

    public function ng_shop_ma($shop_id)
    {
        $data = [
            'appAuthToken' => $this->get_token($shop_id),
        ];
        return $this->post('wmoper/ng/poi/getPoiExtendInfo', $data, 16);
    }

    public function get_token($shop_id, $order_id = '')
    {
        $key = 'meituan:open:token:' . $shop_id;
        $token = Cache::get($key);
        if (!$token) {
            if ($token_data = MeituanOpenToken::where('shop_id', $shop_id)->first()) {
                $token = $token_data->token;
                // $key = 'meituan:open:token:' . $shop_id;
                Cache::put($key, $token);
            }
        }
        if (!$token) {
            $dingding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
            $dingding->sendTextMsg("餐饮服务商token不存在异常,order_id:{$order_id},shop_id:{$shop_id}");
        }
        Log::info("餐饮服务商获取token|order_id:{$order_id},shop_id:{$shop_id},token:{$token}");
        return $token;
    }
}
