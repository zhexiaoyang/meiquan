<?php

namespace App\Jobs\Timer;
use Hhxsv5\LaravelS\Swoole\Timer\CronJob;


class AccountMoneyCronJob extends CronJob
{

    // !!! 定时任务的`interval`和`isImmediate`有两种配置方式（二选一）：一是重载对应的方法，二是注册定时任务时传入参数。
    // --- 重载对应的方法来返回配置：开始
    public function interval()
    {
        return 1800000;
        // return 1000 * 60 * 5;
    }
    public function isImmediate()
    {
        // 是否立即执行第一次，false则等待间隔时间后执行第一次
        return true;
    }
    // --- 重载对应的方法来返回配置：结束
    public function run()
    {
        \Log::info("[执行检查余额任务]-[开始]");
        $h = date("H", time());
        \Log::info("[执行检查余额任务]-[时间]-H:{$h}");
        // $dingding = app("ding");
        // $dingding->sendMarkdownMsgArray("执行检查余额任务");

        if ($h > 6 && $h < 21) {
            // 闪送余额
            $ss = app("shansong");
            $ss_res = $ss->getUserAccount();
            if (isset($ss_res['data']['balance'])) {
                $ss_money = $ss_res['data']['balance'] / 100;
                \Log::info("[检查余额任务]-闪送余额：{$ss_money}");
                if ($ss_money < 2000) {
                    app('easysms')->send('13843209606', [
                        'template' => 'SMS_218028146',
                        'data' => [
                            'money' => $ss_money
                        ],
                    ]);
                    app('easysms')->send('18611683889', [
                        'template' => 'SMS_218028146',
                        'data' => [
                            'money' => $ss_money
                        ],
                    ]);
                }
            }
            // 达达余额
            $dd = app("dada");
            $dd_res = $dd->getUserAccount();
            if (isset($dd_res['result']['deliverBalance'])) {
                $dd_money = $dd_res['result']['deliverBalance'];
                \Log::info("[检查余额任务]-达达余额：{$dd_money}");
                if ($dd_money < 300) {
                    app('easysms')->send('13843209606', [
                        'template' => 'SMS_218028204',
                        'data' => [
                            'money' => $dd_money
                        ],
                    ]);
                }
            }
            // UU余额
            $uu = app("uu");
            $uu_res = $uu->money();
            if (isset($uu_res['AccountMoney'])) {
                $uu_money = (float) $uu_res['AccountMoney'];
                \Log::info("[检查余额任务]-UU余额：{$uu_money}");
                if ($uu_money < 500) {
                    app('easysms')->send('13843209606', [
                        'template' => 'SMS_218028204',
                        'data' => [
                            'money' => $uu_money
                        ],
                    ]);
                }
            }
        }
    }
}
