<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineShop;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QiYueSuoController extends Controller
{
    public function companyAuth(Request $request)
    {
        Log::info("[契约锁回调-公司认证状态回调]-全部参数：", $request->all());
        $id = $request->get("requestId", "");
        $status = intval($request->get("status", 0));

        if ($status === 1) {
            if ($user = User::query()->where("applicant_id", $id)->first()) {
                $user->contract = 1;
                $user->save();
            }
            // if ($shop = Shop::query()->where("applicant_id", $id)->first()) {
            //     $shop->contract = 1;
            //     $shop->save();
            // }
        }

        return $this->success();
    }

    public function contractStatus(Request $request)
    {
        Log::info("[契约锁回调-合同状态回调]-全部参数：", $request->all());
        $content = $request->get("content", "");
        if ($content) {
            $result = openssl_decrypt(base64_decode($content), 'AES-128-ECB', 'MGgrrudkCvQ7UcRW', OPENSSL_RAW_DATA);
            // $result = openssl_decrypt(base64_decode($content), 'AES-128-ECB', 'Al9xUegalRL8eZI7', OPENSSL_RAW_DATA);
            Log::info("[契约锁回调-合同状态回调]-解密参数：", [$result]);
            if (isset($result['contractId']) && isset($result['contractStatus'])) {
                $contract_id = $request['contractId'];
                if ($shop = OnlineShop::query()->where("contract_id", $contract_id)->first()) {
                    if ($result['contractStatus'] === 'COMPLETE') {
                        $shop->contract_status = 1;
                    }
                }
            }
        }
        return $this->success();
    }

    public function shopAuth(Request $request)
    {
        Log::info("[契约锁回调-门店认证状态回调]-全部参数：", $request->all());
        $id = $request->get("requestId", "");
        $status = intval($request->get("status", 0));

        if ($status === 1) {
            if ($shop = OnlineShop::query()->where("applicant_id", $id)->first()) {
                $shop->contract = 1;
                $shop->save();
            }
        }

        return $this->success();
    }

    public function shopContract(Request $request)
    {
        Log::info("[契约锁回调-门店合同状态回调]-全部参数：", $request->all());
        return $this->success();
    }
}
