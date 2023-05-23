<?php

namespace App\Traits;

use App\Http\Requests\Request;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait RiderOrderCancel
{
    public $cancel_rider_order_action_data = [
        1 => '中台后台',
        2 => '美团外卖',
        3 => '饿了么',
        4 => '美团跑腿',
        5 => '蜂鸟跑腿',
        6 => '闪送',
        7 => '美全达',
        8 => '达达',
        9 => 'UU',
        10 => '顺丰',
        11 => '美团众包',
    ];

    /**
     * 取消美团跑腿订单（1）
     */
    public function cancel_rider_order_mt(Order $order)
    {
        $meituan = app("meituan");

        $result = $meituan->delete([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_order_id,
            'cancel_reason_id' => 399,
            'cancel_reason' => '其他原因',
        ]);

        if (isset($result['code'])) {
            if ($result['code'] != 0) {
                return $result['message'];
            }
        } else {
            return '请求接口失败';
        }

        return true;
    }

    /**
     * 取消蜂鸟订单（2）
     */
    public function cancel_rider_order_fn(Order $order)
    {
        $fengniao = app("fengniao");
        $result = $fengniao->cancelOrder([
            'partner_order_code' => $order->order_id,
            'order_cancel_reason_code' => 2,
            'order_cancel_code' => 9,
            'order_cancel_time' => time() * 1000,
        ]);

        if (isset($result['code'])) {
            if ($result['code'] != 200) {
                return $result['msg'];
            }
        } else {
            return '请求失败';
        }

        return true;
    }

    /**
     * 取消闪送订单（3）
     */
    public function cancel_rider_order_ss(Order $order)
    {
        if ($order->shipper_type_ss) {
            $shansong = new ShanSongService(config('ps.shansongservice'));
        } else {
            $shansong = app("shansong");
        }
        $result = $shansong->cancelOrder($order->ss_order_id);

        if (isset($result['status'])) {
            if (($result['status'] != 200) && ($result['msg'] != '订单已经取消')) {
                return $result['msg'];
            }
        } else {
            return '请求失败';
        }

        return true;
    }

    /**
     * 取消美全达（4）
     */
    public function cancel_rider_order_mqd(Order $order)
    {
        $fengniao = app("meiquanda");
        $result = $fengniao->repealOrder($order->mqd_order_id);

        if (isset($result['code'])) {
            if ($result['code'] != 100) {
                return $result['message'];
            }
        } else {
            return '请求失败';
        }

        return true;
    }

    /**
     * 取消达达（5）
     */
    public function cancel_rider_order_dd(Order $order)
    {
        if ($order->shipper_type_dd) {
            $config = config('ps.dada');
            $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
            $dada = new DaDaService($config);
        } else {
            $dada = app("dada");
        }
        $result = $dada->orderCancel($order->order_id);

        if (isset($result['code'])) {
            if ($result['code'] != 0) {
                return $result['msg'];
            }
        } else {
            return '请求失败';
        }

        return true;
    }

    /**
     * 取消UU（6）
     */
    public function cancel_rider_order_uu(Order $order)
    {
        $uu = app("uu");
        $result = $uu->cancelOrder($order);

        if (isset($result['return_code'])) {
            if ($result['return_code'] != 'ok') {
                return $result['return_msg'];
            }
        } else {
            return '请求失败';
        }

        return true;
    }

    /**
     * 取消顺丰（7）
     */
    public function cancel_rider_order_sf(Order $order)
    {
        if ($order->shipper_type_sf) {
            $sf = app("shunfengservice");
        } else {
            $sf = app("shunfeng");
        }
        $result = $sf->cancelOrder($order);

        if (isset($result['error_code'])) {
            if ($result['error_code'] != 0) {
                return $result['error_msg'];
            }
        } else {
            return '请求失败';
        }

        return true;
    }

    /**
     * 取消美团众包（8）
     */
    public function cancelRiderOrderMeiTuanZhongBao(Order $order, $action, $operator = 0)
    {
        // 日志
        $action_text = $this->cancel_rider_order_action_data[$action] ?? '';
        $log_prefix = "[{$action_text}-操作取消跑腿订单|订单号:{$order->order_id}|ID:{$order->id}|订单状态:{$order->status}|众包状态:{$order->zb_status}|ps:{$order->ps}|操作人:{$operator}]";
        Log::info("$log_prefix-开始");
        // 取消美团众包跑腿
        $shop = Shop::find($order->shop->id);
        $zhongbaoapp = null;
        $token = false;
        if ($shop->meituan_bind_platform == 4) {
            $zhongbaoapp = app("minkang");
        } elseif ($shop->meituan_bind_platform == 31) {
            $token = true;
            $zhongbaoapp = app("meiquan");
        }
        // 101512 已选择其他配送方式 (未接单)
        // 102112 已选择其他配送方式 （已接单）
        // 103011 商家自身原因 （已取货）
        $cancel_reason_zb = [ 1 => '101512', 2 => '102112', 3 => '103011'];
        $cancel_reason_msg_zb = [ 1 => '已选择其他配送方式', 2 => '已选择其他配送方式', 3 => '商家自身原因'];
        $cancel_reason_status = 1;
        // zb_status
        if ($order->zb_status == 40 || $order->zb_status == 50) {
            $cancel_reason_status = 2;
        }
        if ($order->zb_status == 60) {
            $cancel_reason_status = 3;
        }
        $result = $zhongbaoapp->cancelLogisticsByWmOrderId(
            $order->order_id,
            $cancel_reason_zb[$cancel_reason_status],
            $cancel_reason_msg_zb[$cancel_reason_status],
            $shop->waimai_mt, $token);
        if ($result['data'] != 'ok' && $cancel_reason_status !== 3) {
            Log::info("$log_prefix-第一次取消美团众包失败", [$result]);
            $cancel_reason_status++;
            $result = $zhongbaoapp->cancelLogisticsByWmOrderId(
                $order->order_id,
                $cancel_reason_zb[$cancel_reason_status] ?? '103011',
                $cancel_reason_msg_zb[$cancel_reason_status] ?? '商家自身原因',
                $shop->waimai_mt, $token);
        }
        if ($result['data'] == 'ok') {
            Order::query()->where('id', $order->id)->update([
                'zb_status' => 99,
                'cancel_at' => date("Y-m-d H:i:s"),
            ]);
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "用户操作取消【美团众包】订单"
            ]);
            Log::info("$log_prefix-取消美团众包成功");
            return [ 'status' => true, 'mes' => '取消美团众包成功'];
            // \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美团众包成功");
        } else {
            Log::info("$log_prefix-第二次取消美团众包失败", [$result]);
        }
        Log::info("$log_prefix-取消失败");
        return [ 'status' => false, 'mes' => '取消美团众包失败'];
    }
}
