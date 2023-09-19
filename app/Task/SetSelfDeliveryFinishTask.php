<?php

namespace App\Task;

use App\Events\OrderComplete;
use App\Jobs\MtLogisticsSync;
use App\Models\Order;
use App\Models\OrderDelivery;
use Hhxsv5\LaravelS\Swoole\Task\Task;

class SetSelfDeliveryFinishTask extends Task
{
    protected $order_id;
    protected $push_at;

    public function __construct($order_id, $push_at)
    {
        $this->order_id = $order_id;
        $this->push_at = $push_at;
    }

    // 处理任务的逻辑，运行在Task进程中，不能投递任务
    public function handle()
    {
        if (!$order = Order::find($this->order_id)) {
            return;
        }
        if ($order->push_at != $this->push_at) {
            \Log::info('push_at错误');
            return;
        }
        if ($order->status != 60) {
            \Log::info('status错误');
            return;
        }
        $order->status = 70;
        $order->over_at = date("Y-m-d H:i:s");
        $order->courier_lng = $order->receiver_lng;
        $order->courier_lat = $order->receiver_lat;
        $order->save();
        // 跑腿运力完成
        OrderDelivery::finish_log($order->id, 200, '2小时自动完成');
        dispatch(new MtLogisticsSync($order));
        event(new OrderComplete($order->id, $order->user_id, $order->shop_id, date("Y-m-d", strtotime($order->created_at))));
    }

    // 可选的，完成事件，任务处理完后的逻辑，运行在Worker进程中，可以投递任务
    // public function finish()
    // {
    //     Task::deliver(new TakeoutOrderVoiceNoticeTask(14, $this->user_id), true);
    // }
}
