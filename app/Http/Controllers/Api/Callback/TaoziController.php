<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsNew;
use App\Models\Shop;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use App\Models\WmPrescriptionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaoziController extends Controller
{
    protected $money = 1;
    protected $expend = 0.6;
    protected $income = 0.4;

    public function order(Request $request)
    {
        $this->log("桃子医院线下处方回调|全部参数：", $request->all());
        $data = $request->get('data');
        $order_id = $request->get('bizID','');
        $status = intval($request->get('code',''));
        if (!empty($data) && in_array($status, [1,2,3,4,5])) {
            $shop_id = $data['thirdUniqueID'];
            $rp = $data['rp'];
            if ($shop = Shop::find($shop_id)) {
                $codes = ['','已开方','拒绝开方','审方通过','审方不通过','处方签章完成'];
                $img = $rp['rpImgFileUrl'] ?? '';
                $rp_time = date('Y-m-d H:i:s', $rp['doctorTime'] / 1000);
                if (!$order = WmPrescription::where('outOrderID', $order_id)->first()) {
                    $_tmp = [
                        'money' => $this->money,
                        'expend' => $this->expend,
                        'income' => $this->income,
                        'status' => 1,
                        'platform' => 3,
                        'shop_id' => $shop->id,
                        'storeName' => $shop->shop_name,
                        'outOrderID' => $order_id,
                        'outRpId' => $rp['clientID'] ?? '',
                        'outDoctorName' => $rp['doctorName'] ?? '',
                        'reviewStatus' => $codes[$status],
                        'rpCreateTime' => $rp_time,
                        'orderStatus' => '-',
                        'image' => $img,
                    ];
                    $order = WmPrescription::create($_tmp);
                } else {
                    // 更改状态
                    $order->reviewStatus = $codes[$status];
                    $order->image = $img;
                    $order->save();
                }
                // 处方详情
                if (!$detail = WmPrescriptionDetail::where('order_id', $order->id)->first()) {
                    $detail = WmPrescriptionDetail::create(['order_id' => $order->id, 'detail' => json_encode($request->all())]);
                }
                $detail->detail = json_encode($request->all());
                $detail->save();
                if ($status == 3) {
                    if (!UserOperateBalance::where(['user_id'=>$shop->user_id, 'tid' => $order->id])->exists()) {
                        $current_user = DB::table('users')->find($shop->user_id);
                        UserOperateBalance::create([
                            "user_id" => $current_user->id,
                            "money" => $this->money,
                            "type" => 2,
                            "before_money" => $current_user->operate_money,
                            "after_money" => ($current_user->operate_money - $this->money),
                            "description" => "线下处方订单：" . $order_id,
                            "shop_id" => $shop->id,
                            "tid" => $order->id,
                            'order_at' => $rp_time,
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
                    }
                }
            } else {
                $this->log("桃子医院线下处方回调|没有门店");
            }
        }

        return $this->status(null, 'success', 0);
    }
}
