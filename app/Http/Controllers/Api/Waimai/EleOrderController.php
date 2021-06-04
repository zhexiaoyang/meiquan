<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EleOrderController extends Controller
{
    public function order(Request $request)
    {
        \Log::info("[饿了么]-[订单回调]，全部参数", $request->all());
    }


    public function auth(Request $request)
    {
        \Log::info("[饿了么]-[授权回调]，全部参数", $request->all());
    }
}
