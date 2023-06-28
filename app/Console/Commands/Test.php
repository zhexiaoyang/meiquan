<?php

namespace App\Console\Commands;

use App\Models\WmOrder;
use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ttttest';

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
        // 获取退款订单
        $orders = WmOrder::select('order_id','id')->where('status', 18)->where('refund_fee', '>', 0)->where('from_type', 31)->where('created_at', '>', '2023-06-01')->get();
        $this->info(count($orders));
        if (!empty($orders)) {
            $minkang = app('minkang');
            foreach ($orders as $order) {
                $res = $minkang->getOrderRefundDetail($order->order_id, false, $order->app_poi_code);
                $refund_settle_amount = 0;
                $refund_platform_charge_fee = 0;
                if (!empty($res['data']) && is_array($res['data'])) {
                    foreach ($res['data'] as $v) {
                        $refund_settle_amount += $v['refund_partial_estimate_charge']['settle_amount'];
                        $refund_platform_charge_fee += $v['refund_partial_estimate_charge']['platform_charge_fee'];
                    }
                    // 更改订单退款信息
                    WmOrder::where('id', $order->id)->update([
                        'refund_settle_amount' => $refund_settle_amount,
                        'refund_platform_charge_fee' => $refund_platform_charge_fee,
                    ]);
                    $this->info("{$order->order_id}更改成功,{$refund_settle_amount},{$refund_platform_charge_fee}");
                } else {
                    $this->info('未获取到退款详情:'. json_encode($res));
                }
            }
        }
    }
}
