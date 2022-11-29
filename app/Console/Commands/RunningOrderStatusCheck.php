<?php

namespace App\Console\Commands;

use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Console\Command;

class RunningOrderStatusCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'running-order-status-check';

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
        // $orders = Order::whereIn("status", [20, 50, 60])->where('push_at', '<', date("Y-m-d H:i:s", time() - 3600 * 2))->orderBy('id')->get();
        $orders = Order::whereIn("status", [20])->where('push_at', '<', date("Y-m-d H:i:s", time() - 3600 * 2))->orderBy('id')->get();
        $this->info("数量：" . $orders->count());
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->status == 20) {
                    $this->cancel_order($order);
                    // $res = $this->cancel_order($order);
                    // \Log::info("res", [$res]);
                    $this->info($order->order_id);
                    // break;
                }
            }
        }
    }

    public function cancel_order(Order $order)
    {
        if (in_array($order->mt_status, [20, 30])) {
            $meituan = app("meituan");
            $result = $meituan->delete([
                'delivery_id' => $order->delivery_id,
                'mt_peisong_id' => $order->mt_order_id,
                'cancel_reason_id' => 399,
                'cancel_reason' => '其他原因',
            ]);
            if ($result['code'] == 0) {
                $order->status = 99;
                $order->mt_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->fn_status, [20, 30])) {
            $fengniao = app("fengniao");
            $result = $fengniao->cancelOrder([
                'partner_order_code' => $order->order_id,
                'order_cancel_reason_code' => 2,
                'order_cancel_code' => 9,
                'order_cancel_time' => time() * 1000,
            ]);
            if ($result['code'] == 200) {
                $order->status = 99;
                $order->fn_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->ss_status, [20, 30])) {
            if ($order->shipper_type_ss) {
                $shansong = new ShanSongService(config('ps.shansongservice'));
            } else {
                $shansong = app("shansong");
            }
            $result = $shansong->cancelOrder($order->ss_order_id);
            if ($result['status'] == 200) {
                $order->status = 99;
                $order->ss_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->mqd_status, [20, 30])) {
            $meiquanda = app("meiquanda");
            $result = $meiquanda->repealOrder($order->mqd_order_id);
            if ($result['code'] == 100) {
                $order->status = 99;
                $order->mqd_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->dd_status, [20, 30])) {
            if ($order->shipper_type_dd) {
                $config = config('ps.dada');
                $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                $dada = new DaDaService($config);
            } else {
                $dada = app("dada");
            }
            $result = $dada->orderCancel($order->order_id);
            if ($result['code'] == 0) {
                $order->status = 99;
                $order->dd_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->uu_status, [20, 30])) {
            $uu = app("uu");
            $result = $uu->cancelOrder($order);
            if ($result['return_code'] == 'ok') {
                $order->status = 99;
                $order->uu_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->sf_status, [20, 30])) {
            if ($order->shipper_type_sf) {
                $sf = app("shunfengservice");
            } else {
                $sf = app("shunfeng");
            }
            $result = $sf->cancelOrder($order);
            if ($result['error_code'] == 0) {
                $order->status = 99;
                $order->sf_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
    }
}
