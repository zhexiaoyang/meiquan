<?php

namespace App\Listeners;

use App\Events\OrderCancel;
use App\Models\Order;
use App\Traits\NoticeTool2;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MeiTuanLogisticsSync implements ShouldQueue
{
    // 订单取消后，同步取消配送到美团
    use NoticeTool2;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderCancel  $event
     * @return void
     */
    public function handle(OrderCancel $event)
    {
        $order_id = $event->order_id;
        $status = $event->status;
        \Log::info("取消订单同步美团状态|order_id：" . $order_id);
        $ps = $event->ps;
        if (!$order = Order::select('id','status','type','order_id','peisong_id','ps','platform')->find($order_id)) {
            return;
        }
        \Log::info("取消订单同步美团状态|order_id：{$order_id}|order_id:{$order->order_id}");
        $meituan = null;
        $type = $order->type;
        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif ($type === 2) {
            $meituan = app("mrx");
        } elseif ($type === 3) {
            $meituan = app("jay");
        } elseif ($type === 4) {
            $meituan = app("minkang");
        } elseif ($type === 5) {
            $meituan = app("qinqu");
        } elseif ($type === 31) {
            $meituan = app("meiquan");
        }
        if (!$meituan) {
            \Log::info("取消订单同步美团状态|order_id：{$order_id}|order_id:{$order->order_id}|没有平台", [$order]);
            return;
        }
        $codes = [ 1 => '10032', 2 => '10004', 3 => '10003', 4 => '10017', 5 => '10002', 6 => '10005', 7 => '10001', 8 => '10032', 200 => '10017'];
        if ($order->platform == 1) {
            if ($status == 99) {
                $params = [
                    'order_id' => $order->order_id,
                    "third_carrier_order_id" => $order->peisong_id ?: $order->order_id,
                    'logistics_provider_code' => $codes[$ps ?: 4],
                    'logistics_status' => 100
                ];
                $this->check_data($meituan->logisticsSync($params), $order);
            }

        } elseif ($order->platform == 2) {
            return;
        }
    }

    public function check_data($result, $order)
    {
        if ($order->platform == 1) {
            if (isset($result['data']) && $result['data'] == 'ok') {
                \Log::info("取消订单同步美团状态|order,{$order->id},{$order->order_id},{$order->ps}");
                return true;
            }
        } elseif ($order->platform == 2) {
            return true;
        }
        \Log::info("取消订单同步美团状态|order,{$order->id},{$order->order_id},{$order->ps}", is_array($result) ? $result : [$result]);
        $this->ding_error("取消订单同步美团状态|order,{$order->id},{$order->order_id},{$order->ps}");
        return false;
    }

    public function failed(OrderCancel $event, $exception)
    {
        //
    }
}
