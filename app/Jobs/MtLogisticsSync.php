<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MtLogisticsSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (in_array($this->order->type, [1,2,3,4])) {

            $type = $this->order->type;

            if ($type === 1) {
                $meituan = app("yaojite");
            } elseif($type === 2) {
                $meituan = app("mrx");
            } elseif($type === 3) {
                $meituan = app("jay");
            } else {
                $meituan = app("minkang");
            }

            $status = 5;

            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 15;
            }elseif ($this->order->status == 60) {
                $status = 20;
            }elseif ($this->order->status == 70) {
                $status = 40;
            }

            $params = [
                "order_id" => $this->order->order_id,
                "courier_name" => $this->order->courier_name,
                "courier_phone" => $this->order->courier_phone,
                "logistics_status" => $status
            ];

            \Log::info('美团外卖同步配送信息开始', []);
            $result = $meituan->logisticsSync($params);
            \Log::info('美团外卖同步配送信息结束', compact("params", "result"));
        } elseif ($this->order->type == 11) {
            $yaogui = app("yaogui");

            $status = 5;

            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 15;
            }elseif ($this->order->status == 60) {
                $status = 20;
            }elseif ($this->order->status == 70) {
                $status = 40;
            }

            $params = [
                "courierName" =>  $this->order->courier_name,
                "orderNo" => $this->order->order_id,
                "courierPhone" => $this->order->courier_phone,
                "logisticsStatus" => $status
            ];
            \Log::info('药柜同步配送信息开始', []);
            $result = $yaogui->logisticsOrder($params);
            \Log::info('药柜同步配送信息结束', compact("params", "result"));
        }
    }
}
