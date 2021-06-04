<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use App\Libraries\Ele\Api\Tool;
use Illuminate\Http\Request;

class EleOrderController extends Controller
{
    public function order(Request $request)
    {
        \Log::info("[饿了么]-[订单回调]，全部参数", $request->all());

        return $this->res('resp.order.create');
    }

    public function auth(Request $request)
    {
        \Log::info("[饿了么]-[授权回调]，全部参数", $request->all());
    }

    public function res($cmd)
    {
        $data = [
            'body' => json_encode([
                'errno' => 0,
                'error' => 'success'
            ]),
            'cmd' => $cmd,
            'source' => config("ps.ele.app_key"),
            'ticket' => Tool::ticket(),
            'timestamp' => time(),
            'version' => 3
        ];

        $data['sign'] = Tool::getSign($data, config("ele.secret"));

        return json_encode($data);
    }
}
