<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanSanFang;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FeiController extends Controller
{
    public $prefix_title = '[美团外卖三方服务商非接单回调-###|订单号:$$$]';

    public function order(Request $request)
    {
        $this->prefix_title = str_replace('###', '订单', $this->prefix_title);
        $this->log('全部参数', $request->all());
        // if (!$data = $request->get('message')) {
        //     return json_encode(['data' => 'OK']);
        // }
        // $data = json_decode($data, true);
        // $order_id = $data['order_id'];
        // $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        // $this->log('全部参数', $data);

        return json_encode(['code' => 0, 'message' => 'success']);
    }

    public function rider(Request $request)
    {
        $this->prefix_title = str_replace('###', '配送状态', $this->prefix_title);
        $this->log('全部参数', $request->all());
        // if (!$data = $request->get('message')) {
        //     return json_encode(['data' => 'OK']);
        // }
        // $data = json_decode($data, true);
        // $order_id = $data['order_id'];
        // $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        // $this->log('全部参数', $data);

        return json_encode(['code' => 0, 'message' => 'success']);
    }

    public function marketing(Request $request)
    {
        $this->prefix_title = str_replace('###', '营销任务', $this->prefix_title);
        $this->log('全部参数', $request->all());
        // if (!$data = $request->get('message')) {
        //     return json_encode(['data' => 'OK']);
        // }
        // $data = json_decode($data, true);
        // $order_id = $data['order_id'];
        // $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        // $this->log('全部参数', $data);

        return json_encode(['code' => 0, 'message' => 'success']);
    }
}
