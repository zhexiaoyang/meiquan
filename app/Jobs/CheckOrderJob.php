<?php


namespace App\Jobs;


use App\Models\Order;
use Hhxsv5\LaravelS\Swoole\Timer\CronJob;

class CheckOrderJob extends CronJob
{
    public function interval()
    {
        return 60000;// 每60秒运行一次
    }

    public function isImmediate()
    {
        return false;// 是否立即执行第一次，false则等待间隔时间后执行第一次
    }

    public function run()
    {
        $orders = Order::where(['status' => -2])->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {

                if ($order->type === 1) {
                    $meituan = app("yaojite");
                } elseif($order->type === 2) {
                    $meituan = app("mrx");
                } else {
                    $meituan = app("jay");
                }

                $res = $meituan->getOrderViewStatus(['order_id' => $order->order_id]);
                if (!empty($res) && is_array($res['data']) && !empty($res['data'])) {
                    $status = isset($res['data']['status']) ? $res['data']['status'] : 0;

                    if ($status > 4) {
                        $order->status = -3;
                        $order->save();
                    }

                    if ($status == 4) {
                        $order->status = -1;
                        $order->save();
                        dispatch(new CreateMtOrder($order));
                    }
                } else {
                    \Log::error('获取订单状态失败', ['order' => $order, 'res' => $res]);
                }
            }
        }
    }
}