<?php


namespace App\Http\Controllers\Api;


use App\Jobs\MtLogisticsSync;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YaoguiController
{
    public function settlement(Request $request)
    {
        Log::info('药柜-结算订单', $request->all());
    }

    public function downgrade(Request $request)
    {
        Log::info('药柜-隐私号降级', $request->all());
    }

    public function create(Request $request)
    {
        Log::info('药柜-创建订单', $request->all());
    }

    public function cancel(Request $request)
    {
        Log::info('药柜-取消订单', $request->all());
    }

    public function urge(Request $request)
    {
        Log::info('药柜-催单', $request->all());
    }
}