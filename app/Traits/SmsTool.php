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
            dispatch(new SendSmsNew($phone, "SMS_267395014", [ 'phone' => '15043264324']));
        }
    }
}
