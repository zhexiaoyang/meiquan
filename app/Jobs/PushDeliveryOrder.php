<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushDeliveryOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $order_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id, $delay)
    {
        $this->order_id = $order_id;
        $this->delay($delay);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $order = Order::find($this->order_id);

        if (!$order || !isset($order->status)) {
            \Log::error("推送预订单错误", [$this->order_id]);
            return;
        }

        if ($order->status != 3 || $order->ps != 0) {
            \Log::info('发送预订单-订单已取消', [$order->toArray()]);
            return;
        }

        // $ding_notice = app("ding");
        // $logs = [
        //     "des" => "发送预订单",
        //     "datetime" => date("Y-m-d H:i:s"),
        //     "order_id" => $this->order->order_id,
        //     "status" => $this->order->status,
        //     "ps" => $this->order->ps
        // ];
        // $res = $ding_notice->sendMarkdownMsgArray("发送预订单", $logs);

        \Log::info('发送预订单', [$order]);
        dispatch(new CreateMtOrder($order));

        // -30 未付款，-20 等待发送，-10 发送失败，0 订单未发送，3 预订单等发送，5：余额不足，10 暂无运力，
        // 20 待接单，30 平台已接单，40 已分配骑手，50 取货中，60 已取货，70 已送达，80 异常，99 已取消，
    }
}
