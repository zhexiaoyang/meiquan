<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Traits\SmsTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrescriptionFeeDeductionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,SmsTool;

    public $order_id;
    public $performanceServiceFee2;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id, $performanceServiceFee2 = null)
    {
        $this->order_id = $order_id;
        $this->performanceServiceFee2 = $performanceServiceFee2;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 处方费扣款逻辑:
        // 1. 如果中台是美团+不审方，系统不扣费；
        // 2. 如果系统抓取到美团处方扣费大于0，系统将自动设置的处方费用为0.2元，且需要把方式设置为 美团+代审方；
        // 3.如果系统抓取美团处方费用等于零，处方费用将按照系统设置的费用为准，且需要把方式设置为美全+代审方。
        $this->log('开始');
        // 处方订单
        $order = DB::table('wm_orders')->find($this->order_id);
        if (!$order->is_prescription) {
            return $this->log("不是处方单{$order->order_id},{$order->id}");
        }
        $shop = DB::table('shops')->find($order->shop_id);
        if (!$shop) {
            $this->log('门店不存在');
            return;
        }
        if (($order->platform == 1) && ($shop->prescription_channel == 4)) {
            // 1. 如果中台是美团+不审方，系统不扣费；
            $this->log("美团不审方：不扣款、不处理|{$order->order_id}|{$shop->id}|{$shop->shop_name}|");
            return;
        }
        // if ($order->platform == 2 && $shop->prescription_channel_ele == 4) {
        //     $this->log("{$shop->id}|{$shop->shop_name}|不扣款、不处理");
        //     return;
        // }
        if ($order->platform != 1 && $order->platform != 2) {
            $this->log('平台错误，退出');
            return;
        }
        // 判断各个平台扣款金额-如果是0，结束任务
        if ($order->platform == 1 && $shop->prescription_cost == 0) {
            $this->log('美团扣款金额0，退出');
            return;
        }
        if ($order->platform == 2 && $shop->prescription_cost_ele == 0) {
            $this->log('饿了么扣款金额0，退出');
            return;
        }
        // 判断是否扣过款
        if ($log = DB::table('user_operate_balances')->where('user_id', $shop->user_id)->where('type2', 2)->where('order_id', $order->id)->first()) {
            $this->log('已经扣款了:' . $log->id);
            return;
        }
        // 判断处方扣款金额是否正确，设置扣款金额
        $performanceServiceFee2 = $this->performanceServiceFee2;
        $this->log("订单获取performanceServiceFee2金额:{$performanceServiceFee2}");
        if ($order->platform == 1) {
            $money = $shop->prescription_cost;
            if (!is_null($performanceServiceFee2)) {
                if ($performanceServiceFee2 == 0) {
                    // 3.如果系统抓取美团处方费用等于零，处方费用将按照系统设置的费用为准，且需要把方式设置为美全+代审方。
                    $money = $shop->prescription_cost;
                    if ($shop->prescription_channel != 1) {
                        DB::table('shops')->where('id', $shop->id)->update([
                            'prescription_channel' => 1,
                        ]);
                    }
                    // $money = 0.8;
                    // 美全+代审方 0.8 元
                    // 处方金额错误
                    // if ((float) $shop->prescription_cost == 0.2) {
                    //     $this->log("修改处方扣款|order:{$order->id},{$order->order_id}|shop:$shop->id,$shop->shop_name");
                    //     DB::table('shops')->where('id', $shop->id)->update([
                    //         'prescription_cost' => 0.8,
                    //         'prescription_channel' => 1,
                    //     ]);
                    //     // 添加修改记录 ？？？
                    //     DB::table('shop_prescription_type_change_logs')->insert([
                    //         'shop_id' => $shop->id,
                    //         'mtid' => $shop->waimai_mt,
                    //         'order_id' => $order->order_id,
                    //         'prescription_cost_old' => $shop->prescription_cost,
                    //         'prescription_channel_old' => $shop->prescription_channel,
                    //         'prescription_cost' => 0.8,
                    //         'prescription_channel' => 1,
                    //         'created_at' => date("Y-m-d H:i:s"),
                    //         'updated_at' => date("Y-m-d H:i:s"),
                    //     ]);
                    // }
                } elseif ($performanceServiceFee2 > 0) {
                    // 2. 如果系统抓取到美团处方扣费大于0，系统将自动设置的处方费用为0.2元，且需要把方式设置为 美团+代审方；
                    // 美团+代审方 0.2 元
                    $money = 0.2;
                    if (((float) $shop->prescription_cost != 0.2) || ($shop->prescription_channel != 2)) {
                        $this->log("修改处方扣款|order:{$order->id},{$order->order_id}|shop:$shop->id,$shop->shop_name");
                        DB::table('shops')->where('id', $shop->id)->update([
                            'prescription_cost' => $money,
                            'prescription_channel' => 2,
                        ]);
                    }
                    // 美团+代审方 0.2 元
                    // 处方金额错误
                    // if ((float) $shop->prescription_cost == 0.8) {
                    //     $this->log("修改处方扣款|order:{$order->id},{$order->order_id}|shop:$shop->id,$shop->shop_name");
                    //     DB::table('shops')->where('id', $shop->id)->update([
                    //         'prescription_cost' => 0.2,
                    //         'prescription_channel' => 2,
                    //     ]);
                    //     // 添加修改记录 ？？？
                    //     DB::table('shop_prescription_type_change_logs')->insert([
                    //         'shop_id' => $shop->id,
                    //         'mtid' => $shop->waimai_mt,
                    //         'order_id' => $order->order_id,
                    //         'prescription_cost_old' => $shop->prescription_cost,
                    //         'prescription_channel_old' => $shop->prescription_channel,
                    //         'prescription_cost' => 0.2,
                    //         'prescription_channel' => 2,
                    //         'created_at' => date("Y-m-d H:i:s"),
                    //         'updated_at' => date("Y-m-d H:i:s"),
                    //     ]);
                    // }
                }
            }
        } else {
            $money = $shop->prescription_cost_ele;
        }
        // 开始扣款
        DB::transaction(function () use ($shop, $order, $money) {
            $this->log("扣款用户门店:门店ID：{$shop->user_id}，门店用户ID：{$shop->user_id}");
            // 扣款用户
            $current_user = DB::table('users')->find($shop->user_id);
            $prescription_data = [
                'money' => $money,
                'expend' => 0,
                'income' => 0,
                'status' => 1,
                'platform' => $order->platform,
                'shop_id' => $shop->id,
                'storeID' => $order->platform == 1 ? $shop->waimai_mt : $shop->waimai_ele,
                'storeName' => $order->wm_shop_name,
                'outOrderID' => $order->order_id,
                'orderStatus' => '已完成',
                'reviewStatus' => '审核成功',
                'orderCreateTime' => date("Y-m-d H:i:s", $order->ctime),
                'rpCreateTime' => date("Y-m-d H:i:s", $order->ctime),
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ];
            // 创建处方订单
            // $data = WmPrescription::create($prescription_data);
            $prescription_id = DB::table('wm_prescriptions')->insertGetId($prescription_data);
            // 减去用户运营余额
            DB::table('users')->where('id', $current_user->id)->decrement('operate_money', $money);
            // 添加扣款记录
            DB::table('user_operate_balances')->insert([
                "user_id" => $current_user->id,
                "money" => $money,
                "type" => 2,
                "before_money" => $current_user->operate_money,
                "after_money" => ($current_user->operate_money - $money),
                "description" => "处方单审方：" . $order->order_id,
                "shop_id" => $shop->id,
                "order_id" => $order->id,
                "tid" => $prescription_id,
                "type2" => 2,
                'order_at' => date("Y-m-d H:i:s", $order->ctime),
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            // UserOperateBalance::create();
            if ($current_user->operate_money < (config('ps.sms_operate_remind.max') + $money)) {
                // $this->prescriptionSms($current_user->phone, ($current_user - $money));
                $this->prescriptionSms($current_user->phone, ($current_user->operate_money - $money) > 0 ? ($current_user->operate_money - $money) : 0);
            }
            $this->log('扣款成功');
            if ($current_user->operate_money < $money) {
                $shop = Shop::select('id', 'user_id', 'yunying_status')->find($order->shop_id);
                if ($shop->yunying_status) {
                    StoreRestJob::dispatch($shop->id);
                }
            }
        });
    }

    public function log($message, $data = [])
    {
        Log::info("处方扣款任务-{$this->order_id}|{$message}", $data);
    }
}
