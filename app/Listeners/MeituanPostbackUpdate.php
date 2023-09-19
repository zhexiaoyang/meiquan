<?php

namespace App\Listeners;

use App\Events\OrderComplete;
use App\Models\Order;
use App\Models\ShopPostback;
use App\Models\WmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MeituanPostbackUpdate implements ShouldQueue
{
    // 自配送回传数据统计
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderComplete  $event
     * @return void
     */
    public function handle(OrderComplete $event)
    {
        $date = $event->date;
        $shop_id = $event->shop_id;
        $where = [
            ['shop_id', '=', $shop_id],
            ['platform', '=', 1],
            ['status', '=', 70],
            ['created_at', '>=', $date],
            ['created_at', '<', date("Y-m-d", strtotime($date) + 86400)],
        ];
        $orders = Order::select('id', 'post_back')->where($where)->get();
        if ($orders->isNotEmpty()) {
            $total = 0;
            $success = 0;
            $fail = 0;
            foreach ($orders as $order) {
                $total++;
                $order->post_back ? $success++ : $fail++;
            }
            ShopPostback::updateOrCreate(
                [
                    'shop_id' => $shop_id,
                    'date' => $date
                ],
                [
                    'shop_id' => $shop_id,
                    'date' => $date,
                    'success' => $success,
                    'fail' => $fail,
                    'rate' => round($success / $total * 100, 2),
                ]
            );
        }
    }
}
