<?php

namespace App\Http\Controllers\MeiTuan;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController
{
    public function status(Request $request)
    {
        $status = $request->get('status', '');
        $shop_id = $request->get('shop_id', 0);
        $res = ['code' => 1];
        if (($shop = Shop::where('id', $shop_id)->first()) && in_array($status, [10, 20, 30, 40])) {
            if ($status == 40) {
                $shop->status = 40;
                $shop->shop_id = $shop->id;
            }
            if ($shop->save()) {
                // $res = ['code' => 0];
                // if ($status == 40) {
                //     $shop->load(['user']);
                //     $phone =  $shop->user->phone ?? 0;
                //
                //     if ($phone) {
                //         try {
                //             app('easysms')->send($phone, [
                //                 'template' => 'SMS_186400271',
                //                 'data' => [
                //                     'name' => $phone,
                //                     'title' => $shop->shop_name
                //                 ],
                //             ]);
                //         } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                //             $message = $exception->getException('aliyun')->getMessage();
                //             \Log::info('审核通过发送短信异常', [$phone, $message]);
                //         }
                //     }
                // }
            }
        }
        \Log::info('美团门店状态回调', ['status' => $status, 'shop_id' => $shop_id, 'res' => $res]);
        return json_encode($res);
    }
}