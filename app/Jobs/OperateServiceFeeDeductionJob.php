<?php

namespace App\Jobs;

use App\Traits\SmsTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class OperateServiceFeeDeductionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SmsTool;

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
        // 外卖订单
        $order_id = $this->order_id;
        $order = DB::table('wm_orders')->find($order_id);

        if (!$order) {
            return;
        }
        if ($order->operate_service_fee_status) {
            return;
        }

        DB::transaction(function () use ($order) {
            $money = $order->operate_service_fee;
            $user_id = $order->user_id;
            // 修改外卖订单扣运营服务费状态
            DB::table('wm_orders')->where('id', $order->id)->where('operate_service_fee_status', 0)->update([
                'operate_service_fee_status' => 1,
                'operate_service_fee_at' => date("Y-m-d H:i:s")
            ]);
            // 查找当前用户
            $current_user = DB::table('users')->find($user_id);
            // 减去用户运营余额
            DB::table('users')->where('id', $user_id)->decrement('operate_money', $money);
            // 添加扣款记录
            DB::table('user_operate_balances')->insert([
                "user_id" => $current_user->id,
                "money" => $money,
                "type" => 2,
                "before_money" => $current_user->operate_money,
                "after_money" => ($current_user->operate_money - $money),
                "description" => "代运营服务费：" . $order->order_id,
                "shop_id" => $order->shop_id,
                "order_id" => $order->id,
                "tid" => $order->id,
                "type2" => 3,
                'order_at' => date("Y-m-d H:i:s", $order->ctime),
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            if ($current_user->operate_money < (config('ps.sms_operate_remind.max') + $money)) {
                $this->prescriptionSms($current_user->phone);
            }
        });
    }
}
