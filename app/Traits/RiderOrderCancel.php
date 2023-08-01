<?php

namespace App\Traits;

use App\Http\Requests\Request;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderDelivery;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait RiderOrderCancel
{
    use NoticeTool2, LogTool2;

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

    public function cancelLogSave($order_id, $ps, $ps_text)
    {
        OrderLog::create([
            'ps' => $ps,
            "order_id" => $order_id,
            "des" => "取消[{$ps_text}]跑腿订单",
        ]);
    }

    /**
     * 取消美团（1）
     */
    public function cancelMeituanOrder($mt_peisong_id, $delivery_id, $order_id, $order_no, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $meituan = app("meituan");
        $cancel_result = $meituan->delete([
            'delivery_id' => $delivery_id,
            'mt_peisong_id' => $mt_peisong_id,
            'cancel_reason_id' => 399,
            'cancel_reason' => '其他原因',
        ]);
        if ($cancel_result['code'] !== 0) {
            $this->ding_error("[{$message}]取消美团订单失败[跑腿订单号:{$order_no}|美团订单号{$mt_peisong_id}]");
            $result = ['status' => false, 'msg' => $cancel_result['message']];
        } else {
            // 记录订单日志
            $this->cancelLogSave($order_id, 1, '美团跑腿');
        }
        return $result;
    }

    /**
     * 取消闪送-聚合（3）
     */
    public function cancelShansongOrder(OrderDelivery $orderDelivery, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $shansong = app("shansong");
        $cancel_result = $shansong->cancelOrder($orderDelivery->three_order_no);
        if ($cancel_result['status'] != 200) {
            $this->ding_error("[{$message}]取消聚合闪送订单失败[跑腿订单ID:{$orderDelivery->order_id}|跑腿订单号:{$orderDelivery->order_no}|闪送订单号{$orderDelivery->three_order_no}]");
            $result = ['status' => false, 'msg' => $result['msg']];
        } else {
            // 记录订单日志
            $this->cancelLogSave($orderDelivery->order_id, 3, '闪送');
        }
        return $result;
    }
    /**
     * 取消闪送-自有（3）
     */
    public function cancelShansongOwnOrder(OrderDelivery $orderDelivery, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $shansong = new ShanSongService(config('ps.shansongservice'));
        $cancel_result = $shansong->cancelOrder($orderDelivery->three_order_no);
        if ($cancel_result['status'] != 200) {
            $this->ding_error("取消自有闪送订单失败[跑腿订单ID:{$orderDelivery->order_id}|跑腿订单号:{$orderDelivery->order_no}|闪送订单号{$orderDelivery->three_order_no}]");
            $result = ['status' => false, 'msg' => $result['msg']];
        } else {
            // 记录订单日志
            $this->cancelLogSave($orderDelivery->order_id, 3, '闪送');
        }
        return $result;
    }

    /**
     * 取消达达-聚合（3）
     */
    public function cancelDadaOrder($order_id, $order_no, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $dada = app("dada");
        $cancel_result = $dada->orderCancel($order_no);
        if ($cancel_result['code'] != 0) {
            $this->ding_error("[{$message}]取消聚合达达订单失败[跑腿订单号:{$order_no}]");
            $result = ['status' => false, 'msg' => $cancel_result['msg']];
        } else {
            $this->cancelLogSave($order_id, 5, '达达');
        }
        return $result;
    }
    /**
     * 取消达达-自有（3）
     */
    public function cancelDadaOwnOrder($shop_id, $order_id, $order_no, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $config = config('ps.dada');
        $config['source_id'] = get_dada_source_by_shop($shop_id);
        $dada = new DaDaService($config);
        $cancel_result = $dada->orderCancel($order_no);
        if ($cancel_result['code'] != 0) {
            $this->ding_error("[{$message}]取消自有达达订单失败[跑腿订单号:{$order_no}]");
            $result = ['status' => false, 'msg' => $cancel_result['msg']];
        } else {
            $this->cancelLogSave($order_id, 5, '达达');
        }
        return $result;
    }

    /**
     * 取消UU（6）
     */
    public function cancelUuOrder($order_id, $order_no, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $uu = app("uu");
        $cancel_result = $uu->cancelOrderByOrderId($order_id);
        if ($cancel_result['return_code'] != 'ok') {
            $this->ding_error("[{$message}]取消UU订单失败[跑腿ID:{$order_id}|跑腿订单号:{$order_no}]");
            $result = ['status' => false, 'msg' => $cancel_result['return_msg']];
        } else {
            // 记录订单日志
            $this->cancelLogSave($order_id, 6, 'UU');
        }
        return $result;
    }

    public function cancelShunfengOrder($shop_id, $delivery_id, $order_id, $order_no, $message = ''): array
    {
        $result = ['status' => true, 'msg' => ''];
        $sf = app("shunfeng");
        $cancel_result = $sf->cancelOrderByOrderId($delivery_id, $shop_id);
        if ($cancel_result['error_code'] != 0) {
            $this->ding_error("[{$message}]取消聚合顺丰订单失败[跑腿ID:{$order_id}|跑腿订单号:{$order_no}]");
            $result = ['status' => false, 'msg' => $cancel_result['error_msg']];
        } else {
            // 记录订单日志
            $this->cancelLogSave($order_id, 7, '顺丰');
        }
        return $result;
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
        $res_reason = $zhongbaoapp->getCancelDeliveryReason($order->order_id, $shop->waimai_mt, $token);
        Log::info("$log_prefix-取消众包获取原因", [$res_reason]);
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
                "des" => $action_text. "操作取消「美团众包」订单"
            ]);
            Log::info("$log_prefix-取消美团众包成功");
            return [ 'status' => true, 'msg' => '取消美团众包成功', 'mes' => '取消美团众包成功'];
            // \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美团众包成功");
        } else {
            Log::info("$log_prefix-第二次取消美团众包失败", [$result]);
        }
        Log::info("$log_prefix-取消失败");
        return [ 'status' => false, 'msg' => '取消美团众包失败', 'mes' => '取消美团众包失败'];
    }
}
