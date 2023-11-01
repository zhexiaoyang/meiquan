<?php

namespace App\Jobs;

use App\Traits\LogTool2;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GetRunningFeeFromMeituanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogTool2;

    public $order_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->log_tool2_prefix = '订单完成获取美团跑腿费';
        // 外卖订单
        $order = DB::table('wm_orders')->find($this->order_id);
        if ($order->from_type != 4 && $order->from_type != 31) {
            return;
        }
        if ($order->running_fee > 0) {
            return;
        }
        $this->log_tool2_prefix = "{$order->order_id}|{$order->id}|订单完成获取美团跑腿费|";
        $mt = null;
        $app_poi_code = '';
        if ($order->from_type == 4) {
            $mt = app("minkang");
        } else if ($order->from_type == 31) {
            $app_poi_code = $order->app_poi_code;
            $mt = app("meiquan");
        }
        $check_zb= $mt->zhongBaoShippingFee($order->order_id, $app_poi_code);
        $money_zb = $check_zb['data'][0]['shipping_fee'] ?? 0;
        if ($money_zb > 0) {
            $this->log_info("众包跑腿费：{$money_zb}");
            DB::table('wm_orders')->where('id', $order->id)->where('running_fee', 0)->update(['running_fee' => $money_zb]);
        }
    }
}
