<?php

namespace App\Listeners;

use App\Events\OrderComplete;
use App\Task\TakeoutOrderVoiceNoticeTask;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeliveryVoiceReminder
{
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
     * @param  OrderComplete  $event
     * @return void
     */
    public function handle(OrderComplete $event)
    {
        // 接收用户ID
        $user_id = $event->user_id;
        // 订单状态（非常规状态，21 超过10分钟没人接单）
        $status = $event->status;
        // 根据订单状态判断声音
        $voices = [70 => 14];
        if (isset($voices[$status])) {
            Task::deliver(new TakeoutOrderVoiceNoticeTask($voices[$status], $user_id), true);
        }
    }
}
