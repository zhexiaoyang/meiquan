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

                        $order->load('shop');
                        $user = User::query()->find($order->shop->user_id);

                        if ($user->money > $order->money && $user->where('money', '>', $order->money)->update(['money' => $user->money - $order->money])) {
                            MoneyLog::query()->create([
                                'order_id' => $order->id,
                                'amount' => $order->money,
                            ]);
                            dispatch(new CreateMtOrder($order));
                            if ($user->money < 20) {
                                try {
                                    app('easysms')->send($user->phone, [
                                        'template' => 'SMS_186380293',
                                        'data' => [
                                            'name' => $user->phone ?? '',
                                            'number' => 20
                                        ],
                                    ]);
                                } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                                    $message = $exception->getException('aliyun')->getMessage();
                                    \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                                }
                            }
                        } else {
                            try {
                                app('easysms')->send($user->phone, [
                                    'template' => 'SMS_186380293',
                                    'data' => [
                                        'name' => $user->phone ?? '',
                                        'number' => 20
                                    ],
                                ]);
                            } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                                $message = $exception->getException('aliyun')->getMessage();
                                \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                            }
                        }
                    }
                } else {
                    \Log::error('获取订单状态失败', ['order' => $order, 'res' => $res]);
                }
            }
        }
    }
}