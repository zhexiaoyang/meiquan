<?php

namespace App\Http\Controllers\Api\Waimai\MinKang;

use App\Http\Controllers\Controller;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public $prefix = '[美团外卖民康-订单回调]';

    public function create(Request $request)
    {
        if ($order_id = $request->get("order_id", "")) {
            // $this->log('全部参数', $request->all());
            $meituan = app("minkang");
            $res = $meituan->orderConfirm($order_id);
            $this->log("create|订单号：{$order_id}|操作接单返回信息", $res);
        }

        return json_encode(['data' => 'ok']);
    }

    // public function confirm(Request $request)
    // {
    //     $this->prefix .= '-[确认]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }
    //
    // public function cancel(Request $request)
    // {
    //     $this->prefix .= '-[取消]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }
    //
    // public function refund(Request $request)
    // {
    //     $this->prefix .= '-[退款]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }
    //
    // public function rider(Request $request)
    // {
    //     $this->prefix .= '-[骑手]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }

    public function finish(Request $request)
    {
        $order_id = $request->get('wm_order_id_view', '');
        $status = $request->get('status', '');

        if ($order_id && $status) {
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 8) {
                    $order->status = 18;
                    $order->save();
                }
                $this->log("finish|订单号：{$order_id}|操作完成");
            } else {
                $this->log("finish|订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }

    // public function partrefund(Request $request)
    // {
    //     $this->prefix .= '-[部分退款]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }
    //
    // public function remind(Request $request)
    // {
    //     $this->prefix .= '-[催单]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }
    //
    // public function down(Request $request)
    // {
    //     $this->prefix .= '-[降级]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }
}
