<?php

namespace App\Jobs;

use App\Models\MoneyLog;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateMtOrder implements ShouldQueue
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
        $meituan = app("meituan");
        $params = [
            'delivery_id' => $this->order->delivery_id,
            'order_id' => $this->order->order_id,
            'shop_id' => $this->order->shop_id,
            'delivery_service_code' => "4011",
            'receiver_name' => $this->order->receiver_name,
            'receiver_address' => $this->order->receiver_address,
            'receiver_phone' => $this->order->receiver_phone,
            'receiver_lng' => $this->order->receiver_lng * 1000000,
            'receiver_lat' => $this->order->receiver_lat * 1000000,
            'coordinate_type' => $this->order->coordinate_type,
            'goods_value' => $this->order->goods_value,
            'goods_weight' => $this->order->goods_weight,
        ];
        $result = $meituan->createByShop($params);
        if ($result['code'] === 0) {
            \DB::table('orders')->where('id', $this->order->id)->update(['mt_peisong_id' => $result['data']['mt_peisong_id'], 'status' => 0]);
        } else {
            $log = MoneyLog::query()->where('order_id', $this->order->id)->first();
            if ($log) {
                $log->status = 2;
                $log->save();
                $shop = \DB::table('shops')->where('shop_id', $this->order->shop_id)->first();
                if (isset($shop->user_id) && $shop->user_id) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
                    \Log::info('创建订单失败，将钱返回给用户', [$this->order->money]);
                } else {
                    \Log::info('创建订单失败，门店不存在', [$shop]);
                }
            }
            \DB::table('orders')->where('id', $this->order->id)->update(['failed' => $result['message'], 'status' => -1]);
        }
    }
}
