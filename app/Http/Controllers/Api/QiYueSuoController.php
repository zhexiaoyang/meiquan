<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            if ($shop = Shop::query()->where("applicant_id", $id)->first()) {
                $shop->contract = 1;
                $shop->save();
            }
        }

        return $this->success();
    }

    public function contractStatus(Request $request)
    {
        Log::info("[契约锁回调-合同状态回调]-全部参数：", $request->all());
        return $this->success();
    }
}
