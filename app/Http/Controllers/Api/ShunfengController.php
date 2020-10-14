<?php


namespace App\Http\Controllers\Api;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShunfengController
{
    public function status(Request $request)
    {
        Log::info('顺丰-订单状态回调', $request->all());

        $res = [
            "error_code" => 0,
            "error_msg" => "success"
        ];

        return json_encode($res);
    }

    public function complete(Request $request)
    {
        Log::info('顺丰-订单完成回调', $request->all());

        $res = [
            "error_code" => 0,
            "error_msg" => "success"
        ];

        return json_encode($res);
    }

    public function cancel(Request $request)
    {
        Log::info('顺丰-订单取消回调', $request->all());

        $res = [
            "error_code" => 0,
            "error_msg" => "success"
        ];

        return json_encode($res);
    }

    public function fail(Request $request)
    {
        Log::info('顺丰-订单异常回调', $request->all());

        $res = [
            "error_code" => 0,
            "error_msg" => "success"
        ];

        return json_encode($res);
    }
}
