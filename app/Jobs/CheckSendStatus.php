<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckSendStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $order;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order, $delay)
    {
        $this->order = $order;
        $this->delay($delay);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ding_notice = app("ding");
        $logs = [
            "datetime" => date("Y-m-d H:i:s"),
            "order_id" => $this->order->order_id,
            "status" => $this->order->status,
            "ps" => $this->order->ps
        ];
        $res = $ding_notice->sendMarkdownMsgArray("执行检查订单发送状态1", $logs);
        \Log::info('钉钉日志发送状态-执行检查订单发送状态', [$res]);

        $order = Order::query()->find($this->order->id);

        $logs = [
            "datetime" => date("Y-m-d H:i:s"),
            "order_id" => $order->order_id,
            "status" => $order->status,
            "ps" => $order->ps
        ];

        $res = $ding_notice->sendMarkdownMsgArray("执行检查订单发送状态2", $logs);
        \Log::info('钉钉日志发送状态-执行检查订单发送状态', [$res]);

        // -30 未付款，-20 等待发送，-10 发送失败，0 订单未发送，5：余额不足，10 暂无运力，
        // 20 待接单，30 平台已接单，40 已分配骑手，50 取货中，60 已取货，70 已送达，80 异常，99 已取消，
        if ($order->status < 40 && $order->status >= 20) {
            $res = $ding_notice->sendMarkdownMsgArray("重新发送订单", $logs);
            \Log::info('钉钉日志发送状态-重新发送订单', [$res]);
        } else {
            $res = $ding_notice->sendMarkdownMsgArray("不重新发送订单", $logs);
            \Log::info('钉钉日志发送状态-不重新发送订单', [$res]);
        }
    }
}
