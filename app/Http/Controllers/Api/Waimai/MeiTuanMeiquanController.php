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
    }
    public function create(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送已确认订单回调]-全部参数：", $request->all());
    }
    public function cancel(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送用户或客服取消订单回调]-全部参数：", $request->all());
    }
    public function refund(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送全额退款信息回调]-全部参数：", $request->all());
    }
    public function refundPart(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送部分退款信息回调]-全部参数：", $request->all());
    }
    public function logistics(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送美配订单配送状态回调]-全部参数：", $request->all());
    }
}
