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

    public function refund(Request $request)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->log('全部退款全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }

    public function partrefund(Request $request)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->log('部分退款全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }

    /**
     * 美配订单状态回调
     * @data 2022/2/26 9:24 上午
     */
    public function rider(Request $request)
    {
        $order_id = $request->get('order_id', '');
        $status = $request->get('logistics_status', '');
        $time = $request->get('time', 0);
        $name = $request->get('dispatcher_name', '');
        $phone = $request->get('dispatcher_mobile', '');
        // $this->log('美配订单状态回调全部参数', $request->all());

        if ($order_id && $status) {
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if (in_array($status, [10, 20, 40]) && $order->status < 16) {
                    if ($status == 10) {
                        $order->status = 12;
                    } elseif ($status == 20) {
                        $order->status = 14;
                    } elseif ($status == 40) {
                        $order->status = 16;
                        $order->send_at = date("Y-m-d H:i:s", $time ?: time());
                    }
                    if ($name) {
                        $order->shipper_name = $name;
                        $order->shipper_phone = $phone;
                    }
                    $order->save();
                    $this->log("status_platform|订单号：{$order_id}|操作完成");
                } else {
                    $this->log("status_platform|订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log("status_platform|订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }

    /**
     * 自配订单状态回调
     * @data 2022/2/26 9:24 上午
     */
    public function status_self(Request $request)
    {
        $order_id = $request->get('order_view_id', '');
        $status = $request->get('logistics_status', '');
        $time = $request->get('operate_time', 0);

        if ($order_id && $status) {
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 20 && $order->status < 14) {
                    $order->send_at = date("Y-m-d H:i:s", $time ?: time());
                    $order->status = 14;
                    $order->save();
                    $this->log("status_self|订单号：{$order_id}|操作完成");
                } else {
                    $this->log("status_self|订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log("status_self|订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }

    /**
     * 完成订单
     * @data 2022/2/26 9:21 上午
     */
    public function finish(Request $request)
    {
        $order_id = $request->get('wm_order_id_view', '');
        $status = $request->get('status', '');

        if ($order_id && $status) {
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 8 && $order->status < 18) {
                    $order->status = 18;
                    $order->finish_at = date("Y-m-d H:i:s");
                    $order->save();
                    $this->log("finish|订单号：{$order_id}|操作完成");
                } else {
                    $this->log("finish|订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log("finish|订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }
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
