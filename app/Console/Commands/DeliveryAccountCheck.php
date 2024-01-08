<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeliveryAccountCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery-account-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("[新执行检查余额任务]-[开始]");
        $h = date("H:i", time());
        \Log::info("[新执行检查余额任务]-[时间]:{$h}");
        // $dingding = app("ding");
        // $dingding->sendMarkdownMsgArray("执行检查余额任务");

        if ($h >= 7 && $h <= 21) {
            // 闪送余额
            $ss = app("shansong");
            $ss_res = $ss->getUserAccount();
            if (isset($ss_res['data']['balance'])) {
                $ss_money = $ss_res['data']['balance'] / 100;
                \Log::info("[检查余额任务]-闪送余额：{$ss_money}");
                if ($ss_money < 1000) {
                    sendTextMessageWeChat("闪送跑腿余额：{$ss_money}，已不足1000元");
                    // app('easysms')->send('13843209606', [
                    //     'template' => 'SMS_218028146',
                    //     'data' => [
                    //         'money' => $ss_money
                    //     ],
                    // ]);
                }
            }
            // 达达余额
            $dd = app("dada");
            $dd_res = $dd->getUserAccount();
            if (isset($dd_res['result']['deliverBalance'])) {
                $dd_money = $dd_res['result']['deliverBalance'];
                \Log::info("[检查余额任务]-达达余额：{$dd_money}");
                if ($dd_money < 1000) {
                    sendTextMessageWeChat("达达跑腿余额：{$dd_money}，已不足1000元");
                    // app('easysms')->send('13843209606', [
                    //     'template' => 'SMS_218028204',
                    //     'data' => [
                    //         'money' => $dd_money
                    //     ],
                    // ]);
                }
            }
            // UU余额
            $uu = app("uu");
            $uu_res = $uu->money();
            if (isset($uu_res['AccountMoney'])) {
                $uu_money = (float) $uu_res['AccountMoney'];
                \Log::info("[检查余额任务]-UU余额：{$uu_money}");
                if ($uu_money < 100) {
                    sendTextMessageWeChat("UU跑腿余额：{$uu_money}，已不足100元");
                    // app('easysms')->send('13843209606', [
                    //     'template' => 'SMS_227743960',
                    //     'data' => [
                    //         'money' => $uu_money
                    //     ],
                    // ]);
                }
            }
            // 顺丰
            $sf = app("shunfeng");
            $sf_res = $sf->getshopaccountbalance();
            if (isset($sf_res['result']['supplier_balance'])) {
                $sf_money = (float) $sf_res['result']['supplier_balance'] / 100;
                \Log::info("[检查余额任务]-顺丰余额：{$sf_money}");
                if ($sf_money < 1000) {
                    sendTextMessageWeChat("顺丰跑腿余额：{$sf_money}，已不足1000元");
                    // app('easysms')->send('13843209606', [
                    //     'template' => 'SMS_227743960',
                    //     'data' => [
                    //         'money' => $uu_money
                    //     ],
                    // ]);
                }
            }
        }
    }
}
