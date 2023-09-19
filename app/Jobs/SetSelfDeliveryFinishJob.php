<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderDelivery;
use App\Task\TakeoutOrderVoiceNoticeTask;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SetSelfDeliveryFinishJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order_id;
    protected $push_at;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id, $push_at, $ttl)
    {
        $this->delay = $ttl;
        $this->push_at = $push_at;
        $this->order_id = $order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
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
        Task::deliver(new TakeoutOrderVoiceNoticeTask(14, $order->user_id), true);
    }
}
