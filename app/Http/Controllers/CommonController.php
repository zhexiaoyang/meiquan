<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Overtrue\EasySms\EasySms;

class CommonController extends Controller
{


    /**
     * 发送短信验证码
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function getVerifyCode(Request $request)
    {
        $request->validate([
            'phone' => [
                'required',
                'regex:/^((13[0-9])|(14[5,7])|(15[0-3,5-9])|(17[0,3,5-8])|(18[0-9])|166|198|199)\d{8}$/'
            ]
        ]);

        $phone = $request->phone;

        // 生成4位随机数，左侧补0
        $code = str_pad(random_int(1, 9999), 4, 0, STR_PAD_LEFT);

        try {
            app('easysms')->send($phone, [
                'template' => 'SMS_186405048',
                'data' => [
                    'code' => $code
                ],
            ]);
        } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            $message = $exception->getException('aliyun')->getMessage();
            \Log::info('注册短信验证码发送异常', [$phone, $message]);
            return $this->error($message ?: '短信发送异常');
        }

        $key = $phone;
        $expiredAt = now()->addMinutes(5);
        // 缓存验证码 5 分钟过期。
        Cache::put($key, ['phone' => $phone, 'code' => $code], $expiredAt);

        return $this->success();
    }

    public function agreement()
    {
        $agreements = Agreement::query()->select('id','title','cover','url','date')->where('status', 1)->get();
        return $this->success($agreements);
    }
}
