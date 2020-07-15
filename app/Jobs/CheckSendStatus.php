<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Shop;
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

        if ($this->order->status >= 40) {
            return;
        }

        $res = $ding_notice->sendMarkdownMsgArray("执行检查订单发送状态", $logs);
        \Log::info('钉钉日志发送状态-执行检查订单发送状态', [$res]);

        // -30 未付款，-20 等待发送，-10 发送失败，0 订单未发送，5：余额不足，10 暂无运力，
        // 20 待接单，30 平台已接单，40 已分配骑手，50 取货中，60 已取货，70 已送达，80 异常，99 已取消，
        if ($this->order->status < 40 && $this->order->status >= 20) {
            $res = $ding_notice->sendMarkdownMsgArray("重新发送订单", $logs);
            $shop = Shop::query()->find($this->order->shop_id);
            \Log::info('钉钉日志发送状态-重新发送订单', [$res]);
            if ($this->order->ps == 1) {
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $this->order->delivery_id,
                    'mt_peisong_id' => $this->order->peisong_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);

                if ($result['code'] === 0) {
                    if (Order::query()->where(['id' => $this->order->id])->where('status', '<>', 99)->update(['fail_mt' => "长时间未接单，换配送方式", "ps" => 0])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
                        \Log::info('更换配送方式-取消美团订单成功-将钱返回给用户', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('更换配送方式-取消美团订单成功-将钱返回给用户-失败了', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    dispatch(new CreateMtOrder($this->order));
                }

            } elseif ($this->order->ps == 2) {

                $fengniao = app("fengniao");

                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $this->order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);

                if ($result['code'] == 200) {
                    if (Order::query()->where(['id' => $this->order->id])->where('status', '<>', 99)->update(['fail_fn' => "长时间未接单，换配送方式", "ps" => 0])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
                        \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    dispatch(new CreateMtOrder($this->order));
                }
            } elseif ($this->order->ps == 3) {

                $shansong = app("shansong");

                $result = $shansong->cancelOrder($this->order->peisong_id);

                if ($result['status'] == 200) {
                    if (Order::query()->where(['id' => $this->order->id])->where('status', '<>', 99)->update(['fail_ss' => "长时间未接单，换配送方式", "ps" => 0])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
                        \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    dispatch(new CreateMtOrder($this->order));
                }
            }
        } else {
            $res = $ding_notice->sendMarkdownMsgArray("不重新发送订单", $logs);
            \Log::info('钉钉日志发送状态-不重新发送订单', [$res]);
        }
    }
}
