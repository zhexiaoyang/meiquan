<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeiTuanMeiquanController extends Controller
{
    /**
     * 推送已支付订单回调
     * @param Request $request
     * @author zhangzhen
     * @data 2021/3/12 10:11 下午
     */
    public function pay(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送已支付订单回调URL]-全部参数：", $request->all());
        return 200;
    }
    public function create(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送已确认订单回调]-全部参数：", $request->all());
        return 200;
    }
    public function cancel(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送用户或客服取消订单回调]-全部参数：", $request->all());
        return 200;
    }
    public function refund(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送全额退款信息回调]-全部参数：", $request->all());
        return 200;
    }
    public function refundPart(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送部分退款信息回调]-全部参数：", $request->all());
        return 200;
    }
    public function logistics(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送美配订单配送状态回调]-全部参数：", $request->all());
        return 200;
    }

    // public function test(Request $request)
    // {
    //     $id = $request->get("id");
    //     $type = $request->get("type");
    //     $meiquan = app("meiquan");
    //     $res = '';
    //
    //     if ($type == 1) {
    //         $res = $meiquan->waimaiOrderConfirm(['order_id' => $id]);
    //     } elseif ($type == 2) {
    //         $res = $meiquan->waimaiOrderCancel(['order_id' => $id]);
    //     } elseif ($type == 3) {
    //         $res = $meiquan->waimaiOrderRefundAgree(['order_id' => $id, 'reason' => '同意退款']);
    //     } elseif ($type == 4) {
    //         $res = $meiquan->waimaiOrderRefundReject(['order_id' => $id, 'reason' => '拒绝退款']);
    //     } elseif ($type == 5) {
    //         $res = $meiquan->waimaiOrderBatchPullPhoneNumber(['order_id' => $id, 'offset' => 0, 'limit' => 100]);
    //     } elseif ($type == 6) {
    //         $res = $meiquan->waimaiOrderReviewAfterSales(['wm_order_id_view' => $id, 'review_type' => 1]);
    //     }
    //
    //     return $res;
    //
    // }
}
