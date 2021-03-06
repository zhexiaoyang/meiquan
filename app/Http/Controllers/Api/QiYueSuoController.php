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
            // $result = openssl_decrypt(base64_decode($content), 'AES-128-ECB', 'MGgrrudkCvQ7UcRW', OPENSSL_RAW_DATA);
            $result = openssl_decrypt(base64_decode($content), 'AES-128-ECB', 'Al9xUegalRL8eZI7', OPENSSL_RAW_DATA);
            Log::info("[契约锁回调-合同状态回调]-解密参数1：", [$result]);
            $result = json_decode($result, true);
            Log::info("[契约锁回调-合同状态回调]-解密参数2：", [$result]);
            if (isset($result['contractId']) && isset($result['contractStatus']) && ($result['contractStatus'] === 'COMPLETE')) {
                Log::info("[契约锁回调-合同状态回调]-合同状态：COMPLETE");
                $contract_id = $result['contractId'];
                if ($shop = OnlineShop::query()->where("contract_id", $contract_id)->first()) {
                    Log::info("[契约锁回调-合同状态回调]-合同状态，更改");
                    $shop->contract_status = 1;
                    $shop->save();
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
            Log::info("[契约锁回调-门店认证状态回调]-状态为：1");
            if ($shop = OnlineShop::query()->where("contract_auth_id", $id)->first()) {
                Log::info("[契约锁回调-门店认证状态回调]-门店ID：{$shop->id}");
                $shop->contract_auth = 2;
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
