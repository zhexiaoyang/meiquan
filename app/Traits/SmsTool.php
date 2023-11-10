<?php

namespace App\Traits;

use App\Jobs\SendSmsNew;
use Illuminate\Support\Facades\Cache;

trait SmsTool
{
    public function prescriptionSms($phone)
    {
        // 处方余额不足短信
        // 每天只能发送一条处方余额短信短信
        $lock = Cache::lock("send_sms_chufang:{$phone}", 86400);
        if ($lock->get()) {
            // 参数 phone 客服
            if (!\DB::table('send_sms_logs')->where('phone', $phone)->where('type', 2)->first()) {
                \DB::table('send_sms_logs')->insert([
                    'phone' => $phone,
                    'type' => 2,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s")
                ]);
                dispatch(new SendSmsNew($phone, "SMS_267395014", [ 'phone' => '15043264324']));
            }
        }
    }

    // public function operateMoneySms($phone)
    // {
    //     // 运营余额短信
    //     // 每天只能发送一条处方余额短信短信
    //     $lock = Cache::lock("send_sms_chufang:{$phone}", 86400);
    //     if ($lock->get()) {
    //         // 参数 phone 客服
    //         dispatch(new SendSmsNew($phone, "SMS_267395014", [ 'phone' => '15043264324']));
    //     }
    // }
}
