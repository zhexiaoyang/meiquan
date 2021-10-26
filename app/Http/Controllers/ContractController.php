<?php

namespace App\Http\Controllers;

use App\Libraries\QiYue\QiYue;
use App\Models\ContractOrder;
use App\Models\OnlineShop;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function auth(Request $request)
    {
        if (!$company_name = $request->get("company_name")) {
            return $this->error("公司名称不能为空");
        }
        if (!$applicant = $request->get("applicant")) {
            return $this->error("认证人不能为空");
        }
        if (!$applicant_phone = $request->get("applicant_phone")) {
            return $this->error("认证人电话不能为空");
        }

        $user = $request->user();
        if ($user->contract_auth === 0) {
            $user->company_name = $company_name;
            $user->applicant = $applicant;
            $user->applicant_phone = $applicant_phone;
            $user->contract_auth = 1;
            $user->save();
        }

        $config = config('qiyuesuo');
        $q = new QiYue($config);
        $res = $q->companyauth($user);

        if (!isset($res['code']) || ($res['code'] !== 0)) {
            return $this->error("认证失败，请稍后再试");
        }

        $user->contract_auth_id = $res['result']['requestId'];
        $user->save();

        return $this->success($res);
    }

    public function userSign(Request $request)
    {
        $user = $request->user();

        if ($user->contract_auth != 2) {
            return $this->error("未通过认证，不能签署合同");
        }

        $config = config('qiyuesuo');
        $q = new QiYue($config);

        if (!$user->contract_id) {
            $res = $q->draft($user);
            if (isset($res['code']) && $res['code'] === 0) {
                $user->contract_id = $res['result']['id'];
                $user->save();
            } else {
                return $this->error("系统错误，请稍后再试");
            }
        }

        $res = $q->contract($user);

        return $this->success(['url' => $res['result']['pageUrl']]);

    }

    /**
     * 用户可签合同门店列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 9:38 上午
     */
    public function shops(Request $request)
    {
        $page_size = intval($request->get("page_size", 10));
        $user = $request->user();

        $shops = OnlineShop::query()->where([
            "user_id" => $user->id,
            "status" => 40
        ])->paginate($page_size);

        return $this->page($shops);
    }

    /**
     * 门店合同认证
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 9:24 上午
     */
    public function shopAuth(Request $request)
    {
        if (!$company_name = $request->get("company_name")) {
            return $this->error("公司名称不能为空");
        }
        if (!$applicant = $request->get("applicant")) {
            return $this->error("认证人不能为空");
        }
        if (!$applicant_phone = $request->get("applicant_phone")) {
            return $this->error("认证人电话不能为空");
        }

        if (!$shop = OnlineShop::find(intval($request->get("shop_id")))) {
            return $this->error("门店ID不能为空");
        }

        $user = $request->user();

        $order = ContractOrder::where("user_id", $user->id)->where("online_shop_id", 0)->first();

        if (!$order) {
            return $this->error("次数不足，请先去商城购买电子合同签章次数");
        }

        if ($shop->contract_auth <= 1) {
            $shop->company_name = $company_name;
            $shop->applicant = $applicant;
            $shop->applicant_phone = $applicant_phone;
            $shop->contract_auth = 1;
            $shop->save();
            $order->shop_id = $shop->shop_id;
            $order->online_shop_id = $shop->id;
            $order->save();
        }

        $config = config('qiyuesuo');
        $q = new QiYue($config);
        $res = $q->shopAuth($shop);

        if (!isset($res['code']) || ($res['code'] !== 0)) {
            return $this->error("认证失败，请稍后再试");
        }

        $shop->contract_auth_id = $res['result']['requestId'];
        $shop->save();

        return $this->success($res);
    }

    /**
     * 门店合同签署
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 9:24 上午
     */
    public function shopSign(Request $request)
    {
        if (!$shop = OnlineShop::find(intval($request->get("shop_id")))) {
            return $this->error("门店ID不能为空");
        }

        if ($shop->contract_auth != 2) {
            return $this->error("未通过认证，不能签署合同");
        }

        $config = config('qiyuesuo');
        $q = new QiYue($config);

        if (!$shop->contract_id) {
            $res = $q->shopDraft($shop);
            if (isset($res['code']) && $res['code'] === 0) {
                $shop->contract_id = $res['result']['id'];
                $shop->save();
            } else {
                return $this->error("系统错误，请稍后再试");
            }
        }

        $res = $q->shopContract($shop);

        return $this->success(['url' => $res['result']['pageUrl']]);

    }
}
