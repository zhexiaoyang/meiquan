<?php


namespace App\Jobs;


use App\Models\MoneyLog;
use App\Models\Order;
use App\Models\User;
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
        $orders = Order::where(['status' => -30])->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {

                if ($order->type === 1) {
                    $meituan = app("yaojite");
                } elseif($order->type === 2) {
                    $meituan = app("mrx");
                } elseif($order->type === 3) {
                    $meituan = app("jay");
                } else {
                    $meituan = app("minkang");
                }

                $res = $meituan->getOrderViewStatus(['order_id' => $order->order_id]);
                if (!empty($res) && is_array($res['data']) && !empty($res['data'])) {
                    $status = isset($res['data']['status']) ? $res['data']['status'] : 0;

                    // 1 用户已提交订单 ，2 向商家推送订单 ，3 商家已收到 ，4 商家已确认 ，6 订单配送中 ，7 订单已送达 ，8 订单已完成 ，9 订单已取消
                    // -30 未付款， ，-20 等待发送， ，-10 发送失败， ，0 订单未发送， ，5：余额不足， ，10 暂无运力， ，20 待接单， ，30 平台已接单，
                    // 40 已分配骑手， ，50 取货中， ，60 已取货， ，70 已送达， ，80 异常， ，99 已取消，
                    if ($status > 4) {
                        $order->status = -10;
                        $order->save();
                    }

                    if ($status == 4) {
                        $order->status = 0;
                        if ($order->save()) {
                            dispatch(new CreateMtOrder($order));
                        }

                        // $order->load('shop');
                        // $user = User::query()->find($order->shop->user_id);
                        //
                        // if ($user->money > $order->money && User::query()->where('id', $user->id)->where('money', '>', $order->money)->update(['money' => $user->money - $order->money])) {
                        //     MoneyLog::query()->create([
                        //         'order_id' => $order->id,
                        //         'amount' => $order->money,
                        //     ]);
                        //     dispatch(new CreateMtOrder($order));
                        //     $user = User::query()->find($order->shop->user_id);
                        //     if ($user->money < 20) {
                        //         try {
                        //             app('easysms')->send($user->phone, [
                        //                 'template' => 'SMS_186380293',
                        //                 'data' => [
                        //                     'name' => $user->phone ?? '',
                        //                     'number' => 20
                        //                 ],
                        //             ]);
                        //         } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                        //             $message = $exception->getException('aliyun')->getMessage();
                        //             \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                        //         }
                        //     }
                        // } else {
                        //     $order->status = 200;
                        //     $order->save();
                        //
                        //     try {
                        //         app('easysms')->send($user->phone, [
                        //             'template' => 'SMS_186380293',
                        //             'data' => [
                        //                 'name' => $user->phone ?? '',
                        //                 'number' => 20
                        //             ],
                        //         ]);
                        //     } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                        //         $message = $exception->getException('aliyun')->getMessage();
                        //         \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                        //     }
                        // }
                    }
                } else {
                    \Log::error('获取订单状态失败', ['order' => $order, 'res' => $res]);
                }
            }
        }
    }
}