<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsNew;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaoziController extends Controller
{
    protected $money = 1.5;
    protected $expend = 0.8;
    protected $income = 0.7;

    public function order(Request $request)
    {
        $this->log("桃子医院线下处方回调|全部参数：", $request->all());
        $data = $request->all();
        $order_id = $data['bizID'] ?? '';
        $code = $data['code'] ?? '';

        if ($code == 3) {
            if (!$order = WmPrescription::where('outOrderID', $order_id)->first()) {
                $this->log("桃子医院线下处方回调|订单不存在，order_id：{$order_id}");
            }
            $order->money = $this->money;
            $order->expend = $this->expend;
            $order->income = $this->income;
            $order->orderStatus = '已完成';
            $order->reviewStatus = '审核成功';
            $order->rpCreateTime = date("Y-m-d H:i:s");
            $order->save();
            $shop = DB::table('shops')->find($order->shop_id);
            $current_user = DB::table('users')->find($shop->user_id);
            UserOperateBalance::create([
                "user_id" => $current_user->id,
                "money" => $this->money,
                "type" => 2,
                "before_money" => $current_user->operate_money,
                "after_money" => ($current_user->operate_money - $this->money),
                "description" => "处方单审方：" . $order_id,
                "shop_id" => $shop->id,
                "tid" => $order->id,
                'order_at' => date("Y-m-d H:i:s")
            ]);
            // 减去用户运营余额
            DB::table('users')->where('id', $current_user->id)->decrement('operate_money', $this->money);
            $_user = DB::table('users')->find($current_user->id);
            if ($_user->operate_money < 50) {
                $phone = $_user->phone;
                $lock = Cache::lock("send_sms_chufang:{$phone}", 3600);
                if ($lock->get()) {
                    Log::info("处方余额不足发送短信：{$phone}");
                    dispatch(new SendSmsNew($phone, "SMS_227744641", ['money' => 50, 'phone' => '15843224429']));
                } else {
                    Log::info("今天已经发过短信了：{$phone}");
                }
            }
        } elseif ($code == 2) {
            if (!$order = WmPrescription::where('outOrderID', $order_id)->first()) {
                $this->log("桃子医院线下处方回调|订单不存在，order_id：{$order_id}");
            }
            $order->orderStatus = '已取消';
            $order->reviewStatus = '医生拒绝开处方';
            $order->rpCreateTime = date("Y-m-d H:i:s");
            $order->save();
        } elseif ($code == 4) {
            if (!$order = WmPrescription::where('outOrderID', $order_id)->first()) {
                $this->log("桃子医院线下处方回调|订单不存在，order_id：{$order_id}");
            }
            $order->orderStatus = '已取消';
            $order->reviewStatus = '药师审方不通过';
            $order->rpCreateTime = date("Y-m-d H:i:s");
            $order->save();
        }

        return $this->status(null, 'success', 0);
    }
}
