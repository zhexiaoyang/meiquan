<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

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

        // $logs = [
        //     "datetime" => date("Y-m-d H:i:s"),
        //     "order_id" => $this->order->order_id,
        //     "status" => $this->order->status,
        //     "ps" => $this->order->ps
        // ];

        if ($this->order->status >= 40) {
            \Log::info("[检查发单]-[订单ID：{$this->order->id}-订单号：{$this->order->order_id}]-状态：{$this->order->status},跳过检查");
            return;
        }

        // $res = $ding_notice->sendMarkdownMsgArray("执行检查订单发送状态", $logs);
        // \Log::info('钉钉日志发送状态-执行检查订单发送状态', [$res]);

        // -30 未付款，-20 等待发送，-10 发送失败，0 订单未发送，5：余额不足，10 暂无运力，
        // 20 待接单，30 平台已接单，40 已分配骑手，50 取货中，60 已取货，70 已送达，80 异常，99 已取消，
        if ($this->order->status == 20 || $this->order->status == 30) {
            // $res = $ding_notice->sendMarkdownMsgArray("重新发送订单", $logs);
            // $shop = Shop::query()->find($this->order->shop_id);
            // \Log::info('钉钉日志发送状态-重新发送订单', [$res]);
            \Log::info("[检查发单]-订单ID：{$this->order->id}-订单号：{$this->order->order_id}");

            $setting = OrderSetting::where("shop_id", $this->order->shop_id)->first();
            if ($setting) {
                $order_ttl = $setting->delay_reset * 60;
                $order_type = $setting->type;
            } else {
                $order_ttl = config("ps.shop_setting.delay_reset") * 60;
                $order_type = config("ps.shop_setting.type");
            }

            if ($this->order->push_at) {
                // 订单发送时间
                $push_time = strtotime($this->order->push_at);
                // 检查订单状态时间
                $ttl_time = $order_ttl;
                if (time() <= ($push_time + $ttl_time - 60)) {
                    \Log::info("[检查发单]-[时间不满足]-订单ID：{$this->order->id}-订单号：{$this->order->order_id}");
                    return;
                }
            }

            if ($order_type === 2) {
                if (in_array($this->order->mt_status, [20, 30])) {
                    $meituan = app("meituan");
                    $result = $meituan->delete([
                        'delivery_id' => $this->order->delivery_id,
                        'mt_peisong_id' => $this->order->mt_order_id,
                        'cancel_reason_id' => 399,
                        'cancel_reason' => '其他原因',
                    ]);
                    if ($result['code'] == 0) {
                        DB::table('orders')->where('id', $this->order->id)->update(['fail_mt' => '长时间未接单，换配送方式', 'mt_status' => 7, 'status' => 7]);
                        OrderLog::create([
                            "ps" => 1,
                            "order_id" => $this->order->id,
                            "des" => "长时间未接单，取消【美团】跑腿订单"
                        ]);
                        dispatch(new CreateMtOrder($this->order));
                    }
                }
                if (in_array($this->order->fn_status, [20, 30])) {
                    $fengniao = app("fengniao");
                    $result = $fengniao->cancelOrder([
                        'partner_order_code' => $this->order->order_id,
                        'order_cancel_reason_code' => 2,
                        'order_cancel_code' => 9,
                        'order_cancel_time' => time() * 1000,
                    ]);
                    if ($result['code'] == 200) {
                        DB::table('orders')->where('id', $this->order->id)->update(['fail_fn' => '长时间未接单，换配送方式', 'fn_status' => 7, 'status' => 7]);
                        OrderLog::create([
                            "ps" => 2,
                            "order_id" => $this->order->id,
                            "des" => "长时间未接单，取消【蜂鸟】跑腿订单"
                        ]);
                        dispatch(new CreateMtOrder($this->order));
                    }
                }
                if (in_array($this->order->ss_status, [20, 30])) {
                    $shansong = app("shansong");
                    $result = $shansong->cancelOrder($this->order->ss_order_id);
                    if ($result['status'] == 200) {
                        DB::table('orders')->where('id', $this->order->id)->update(['fail_ss' => '长时间未接单，换配送方式', 'ss_status' => 7, 'status' => 7]);
                        OrderLog::create([
                            "ps" => 3,
                            "order_id" => $this->order->id,
                            "des" => "长时间未接单，取消【闪送】跑腿订单"
                        ]);
                        dispatch(new CreateMtOrder($this->order));
                    }
                }
            } elseif ($order_type === 1) {
                dispatch(new CreateMtOrder($this->order));
            }

            // if ($this->order->ps == 1) {
            //     $meituan = app("meituan");
            //     $result = $meituan->delete([
            //         'delivery_id' => $this->order->delivery_id,
            //         'mt_peisong_id' => $this->order->peisong_id,
            //         'cancel_reason_id' => 399,
            //         'cancel_reason' => '其他原因',
            //     ]);
            //
            //     if ($result['code'] === 0) {
            //         if (Order::query()->where(['id' => $this->order->id])->where('status', '<>', 99)->update(['fail_mt' => "长时间未接单，换配送方式", "ps" => 0])) {
            //             \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
            //             \Log::info('更换配送方式-取消美团订单成功-将钱返回给用户', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
            //         } else {
            //             \Log::info('更换配送方式-取消美团订单成功-将钱返回给用户-失败了', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
            //         }
            //         dispatch(new CreateMtOrder($this->order));
            //     }
            //
            // } elseif ($this->order->ps == 2) {
            //
            //     $fengniao = app("fengniao");
            //
            //     $result = $fengniao->cancelOrder([
            //         'partner_order_code' => $this->order->order_id,
            //         'order_cancel_reason_code' => 2,
            //         'order_cancel_code' => 9,
            //         'order_cancel_time' => time() * 1000,
            //     ]);
            //
            //     if ($result['code'] == 200) {
            //         if (Order::query()->where(['id' => $this->order->id])->where('status', '<>', 99)->update(['fail_fn' => "长时间未接单，换配送方式", "ps" => 0])) {
            //             \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
            //             \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
            //         } else {
            //             \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
            //         }
            //         dispatch(new CreateMtOrder($this->order));
            //     }
            // } elseif ($this->order->ps == 3) {
            //
            //     $shansong = app("shansong");
            //
            //     $result = $shansong->cancelOrder($this->order->peisong_id);
            //
            //     if ($result['status'] == 200) {
            //         if (Order::query()->where(['id' => $this->order->id])->where('status', '<>', 99)->update(['fail_ss' => "长时间未接单，换配送方式", "ps" => 0])) {
            //             \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
            //             \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
            //         } else {
            //             \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $this->order->id, 'money' => $this->order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
            //         }
            //         dispatch(new CreateMtOrder($this->order));
            //     }
            // }
        } else {
            // $res = $ding_notice->sendMarkdownMsgArray("不重新发送订单", $logs);
            \Log::info("[检查发单]-[不重新发送订单]-订单ID：{$this->order->id}-订单号：{$this->order->order_id}");
        }
    }
}
