<?php

namespace App\Http\Controllers;

use App\Libraries\QiYue\QiYue;
use App\Models\Contract;
use App\Models\ContractOrder;
use App\Models\OnlineShop;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public $prefix = '[合同签署]';

    public function index()
    {
        $contracts = Contract::select('id','name','contract_id')->get();

        return $this->success($contracts);
    }
    // public function auth(Request $request)
    // {
    //     if (!$company_name = $request->get("company_name")) {
    //         return $this->error("公司名称不能为空");
    //     }
    //     if (!$applicant = $request->get("applicant")) {
    //         return $this->error("认证人不能为空");
    //     }
    //     if (!$applicant_phone = $request->get("applicant_phone")) {
    //         return $this->error("认证人电话不能为空");
    //     }
    //
    //     $user = $request->user();
    //     if ($user->contract_auth === 0) {
    //         $user->company_name = $company_name;
    //         $user->applicant = $applicant;
    //         $user->applicant_phone = $applicant_phone;
    //         $user->contract_auth = 1;
    //         $user->save();
    //     }
    //
    //     $config = config('qiyuesuo');
    //     $q = new QiYue($config);
    //     $res = $q->companyauth($user);
    //
    //     if (!isset($res['code']) || ($res['code'] !== 0)) {
    //         return $this->error("认证失败，请稍后再试");
    //     }
    //
    //     $user->contract_auth_id = $res['result']['requestId'];
    //     $user->save();
    //
    //     return $this->success($res);
    // }

    // public function userSign(Request $request)
    // {
    //     $user = $request->user();
    //
    //     if ($user->contract_auth != 2) {
    //         return $this->error("未通过认证，不能签署合同");
    //     }
    //
    //     $config = config('qiyuesuo');
    //     $q = new QiYue($config);
    //
    //     if (!$user->contract_id) {
    //         $res = $q->draft($user);
    //         if (isset($res['code']) && $res['code'] === 0) {
    //             $user->contract_id = $res['result']['id'];
    //             $user->save();
    //         } else {
    //             return $this->error("系统错误，请稍后再试");
    //         }
    //     }
    //
    //     $res = $q->contract($user);
    //
    //     return $this->success(['url' => $res['result']['pageUrl']]);
    //
    // }

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

        $contracts = Contract::select('id', 'name')->get()->toArray();

        $shops = OnlineShop::with(['contract'])->where([
            "user_id" => $user->id,
            "status" => 40
        ])->paginate($page_size);

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $data = $contracts;
                foreach ($data as $k => $v) {
                    $data[$k]['status'] = 0;
                    if (!empty($shop->contract)) {
                        foreach ($shop->contract as $item) {
                            if ($v['id'] === $item->contract_id) {
                                $data[$k]['status'] = $item->status;
                            }
                        }
                    }
                }
                unset($shop->contract);
                $shop->contract = $data;
            }
        }

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

        // $order = ContractOrder::query()->where("user_id", $user->id)->where(function ($query) use ($shop) {
        //     $query->where("online_shop_id", 0)->orWhere("online_shop_id", $shop->id);
        // })->orderBy("id")->first();

        // if (!$order) {
        //     $this->log("-[门店认证-合同次数不足]", array_merge($request->all(), ['user' => $user->id]));
        //     return $this->error("次数不足，请先去商城购买电子合同签章次数", 422);
        // }

        if ($shop->contract_auth <= 2) {
            $shop->company_name = $company_name;
            $shop->applicant = $applicant;
            $shop->applicant_phone = $applicant_phone;
            $shop->contract_auth = 1;
            $shop->save();
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
        $contract = $request->get('contract', 1);

        if ($contract == 1) {
            return $this->contract1($request);
        } elseif ($contract == 2) {
            return $this->contract2($request);
        } elseif ($contract == 3) {
            return $this->contract3($request);
        } elseif ($contract == 4) {
            return $this->contract4($request);
        }

        return $this->error("该合同暂未开放签署，请稍等", 422);
    }

    /**
     * 合同ID：1，运营合同
     * @data 2021/12/10 2:04 下午
     */
    public function contract1(Request $request)
    {
        if (!$shop = OnlineShop::find(intval($request->get("shop_id")))) {
            return $this->error("门店ID不能为空");
        }

        if ($shop->contract_auth != 2) {
            return $this->error("未通过认证，不能签署合同", 422);
        }

        $user = $request->user();

        if (!$order = ContractOrder::query()->where([
            ["online_shop_id", $shop->id],
            ["user_id", $user->id],
            ['contract_id', 1],
        ])->first()) {

            $order = ContractOrder::query()->where([
                ["user_id", $user->id],
                ["online_shop_id", 0],
                ['contract_id', 0],
            ])->orderBy("id")->first();

            if (!$order) {
                $this->log("-[签署合同1-合同次数不足]", array_merge($request->all(), ['user' => $user->id]));
                return $this->error("次数不足，请先去商城购买电子合同签章次数", 422);
            }
        }
        $this->log("-[签署合同1]-[合同订单ID：{$order->id}]");

        $config = config('qiyuesuo');
        $q = new QiYue($config);

        if (!$order->three_contract_id) {
            $res = $q->shopDraft($shop);
            if (isset($res['code']) && $res['code'] === 0) {
                $this->log("-[签署合同1-成功]-[合同订单ID：{$order->id}]");
                $order->contract_id = 1;
                $order->three_contract_id = $res['result']['id'];
                $order->shop_id = $shop->shop_id;
                $order->online_shop_id = $shop->id;
                $order->save();
            } else {
                return $this->error($res['message'] ?? "系统错误，请稍后再试");
            }
        }

        $res = $q->shopContract($shop, $order->three_contract_id);

        if (empty($res['message']['pageUrl'])) {
            return $this->error($res['message']);
        }

        return $this->success(['url' => $res['message']['pageUrl'] ?? '']);
    }

    /**
     * 合同ID：1，美团处方-桃子
     * @data 2021/12/10 2:04 下午
     */
    public function contract2(Request $request)
    {
        if (!$shop = OnlineShop::find(intval($request->get("shop_id")))) {
            return $this->error("门店ID不能为空");
        }

        if ($shop->contract_auth != 2) {
            return $this->error("未通过认证，不能签署合同", 422);
        }

        $user = $request->user();

        if (!$order = ContractOrder::query()->where([
            ["online_shop_id", $shop->id],
            ["user_id", $user->id],
            ['contract_id', 2],
        ])->first()) {

            $order = ContractOrder::query()->where([
                ["user_id", $user->id],
                ["online_shop_id", 0],
                ['contract_id', 0],
            ])->orderBy("id")->first();

            if (!$order) {
                $this->log("-[签署合同2-合同次数不足]", array_merge($request->all(), ['user' => $user->id]));
                return $this->error("次数不足，请先去商城购买电子合同签章次数", 422);
            }
        }
        $this->log("-[签署合同2]-[合同订单ID：{$order->id}]");

        $config = config('qiyuesuo');
        $q = new QiYue($config);

        if (!$order->three_contract_id) {
            $res = $q->shopDraftTaozi($shop);
            if (isset($res['code']) && $res['code'] === 0) {
                $this->log("-[签署合同2-草稿成功]-[合同订单ID：{$order->id}]");
                $order->contract_id = 2;
                $order->three_contract_id = $res['result']['id'];
                $order->shop_id = $shop->shop_id;
                $order->online_shop_id = $shop->id;
                $order->save();
            } else {
                return $this->error($res['message'] ?? "系统错误，请稍后再试");
            }
            $res2 = $q->companysignTaozi($order->three_contract_id);
            if (isset($res2['code']) && $res2['code'] === 0) {
                $order->three_sign = 1;
                $order->save();
                $this->log("-[签署合同2-公章成功]-[合同订单ID：{$order->id}]");
            } else {
                return $this->error($res2['message'] ?? "系统错误，请稍后再试");
            }
            $this->log("-[签署公章返回]", [$res2]);
        }

        if ($order->three_sign === 0) {
            $res2 = $q->companysignTaozi($order->three_contract_id);
            if (isset($res2['code']) && $res2['code'] === 0) {
                $order->three_sign = 1;
                $order->save();
                $this->log("-[签署合同2-公章成功]-[合同订单ID：{$order->id}]");
            } else {
                return $this->error($res2['message'] ?? "系统错误，请稍后再试");
            }
            $this->log("-[签署公章返回]", [$res2]);
        }

        $res = $q->shopContract($shop, $order->three_contract_id);

        return $this->success(['url' => $res['result']['pageUrl']]);
    }

    /**
     * 合同ID：1，饿了么处方-桃子
     * @data 2021/12/10 2:04 下午
     */
    public function contract3(Request $request)
    {
        if (!$shop = OnlineShop::find(intval($request->get("shop_id")))) {
            return $this->error("门店ID不能为空");
        }

        if ($shop->contract_auth != 2) {
            return $this->error("未通过认证，不能签署合同", 422);
        }

        $user = $request->user();

        if (!$order = ContractOrder::query()->where([
            ["online_shop_id", $shop->id],
            ["user_id", $user->id],
            ['contract_id', 3],
        ])->first()) {

            $order = ContractOrder::query()->where([
                ["user_id", $user->id],
                ["online_shop_id", 0],
                ['contract_id', 0],
            ])->orderBy("id")->first();

            if (!$order) {
                $this->log("-[签署合同3-合同次数不足]", array_merge($request->all(), ['user' => $user->id]));
                return $this->error("次数不足，请先去商城购买电子合同签章次数", 422);
            }
        }
        $this->log("-[签署合同3]-[合同订单ID：{$order->id}]");

        $config = config('qiyuesuo');
        $q = new QiYue($config);

        if (!$order->three_contract_id) {
            $res = $q->shopDraftTaozi($shop, 2);
            if (isset($res['code']) && $res['code'] === 0) {
                $this->log("-[签署合同3-草稿成功]-[合同订单ID：{$order->id}]");
                $order->contract_id = 3;
                $order->three_contract_id = $res['result']['id'];
                $order->shop_id = $shop->shop_id;
                $order->online_shop_id = $shop->id;
                $order->save();
            } else {
                return $this->error($res['message'] ?? "系统错误，请稍后再试");
            }
            $res2 = $q->companysignTaozi($order->three_contract_id);
            if (isset($res2['code']) && $res2['code'] === 0) {
                $order->three_sign = 1;
                $order->save();
                $this->log("-[签署合同2-公章成功]-[合同订单ID：{$order->id}]");
            } else {
                return $this->error($res2['message'] ?? "系统错误，请稍后再试");
            }
            $this->log("-[签署公章返回]", [$res2]);
        }

        if ($order->three_sign === 0) {
            $res2 = $q->companysignTaozi($order->three_contract_id);
            if (isset($res2['code']) && $res2['code'] === 0) {
                $order->three_sign = 1;
                $order->save();
                $this->log("-[签署合同2-公章成功]-[合同订单ID：{$order->id}]");
            } else {
                return $this->error($res2['message'] ?? "系统错误，请稍后再试");
            }
            $this->log("-[签署公章返回]", [$res2]);
        }

        $res = $q->shopContract($shop, $order->three_contract_id);

        return $this->success(['url' => $res['result']['pageUrl']]);
    }

    /**
     * 合同ID：1，线下处方-桃子
     * @data 2021/12/10 2:04 下午
     */
    public function contract4(Request $request)
    {
        if (!$shop = OnlineShop::find(intval($request->get("shop_id")))) {
            return $this->error("门店ID不能为空");
        }

        if ($shop->contract_auth != 2) {
            return $this->error("未通过认证，不能签署合同", 422);
        }

        $user = $request->user();

        if (!$order = ContractOrder::query()->where([
            ["online_shop_id", $shop->id],
            ["user_id", $user->id],
            ['contract_id', 4],
        ])->first()) {

            $order = ContractOrder::query()->where([
                ["user_id", $user->id],
                ["online_shop_id", 0],
                ['contract_id', 0],
            ])->orderBy("id")->first();

            if (!$order) {
                $this->log("-[签署合同4-合同次数不足]", array_merge($request->all(), ['user' => $user->id]));
                return $this->error("次数不足，请先去商城购买电子合同签章次数", 422);
            }
        }
        $this->log("-[签署合同4]-[合同订单ID：{$order->id}]");

        $config = config('qiyuesuo');
        $q = new QiYue($config);

        if (!$order->three_contract_id) {
            $res = $q->shopDraftTaozi($shop, 3);
            if (isset($res['code']) && $res['code'] === 0) {
                $this->log("-[签署合同4-草稿成功]-[合同订单ID：{$order->id}]");
                $order->contract_id = 4;
                $order->three_contract_id = $res['result']['id'];
                $order->shop_id = $shop->shop_id;
                $order->online_shop_id = $shop->id;
                $order->save();
            } else {
                return $this->error($res['message'] ?? "系统错误，请稍后再试");
            }
            $res2 = $q->companysignTaozi($order->three_contract_id);
            if (isset($res2['code']) && $res2['code'] === 0) {
                $order->three_sign = 1;
                $order->save();
                $this->log("-[签署合同2-公章成功]-[合同订单ID：{$order->id}]");
            } else {
                return $this->error($res2['message'] ?? "系统错误，请稍后再试");
            }
            $this->log("-[签署公章返回]", [$res2]);
        }

        if ($order->three_sign === 0) {
            $res2 = $q->companysignTaozi($order->three_contract_id);
            if (isset($res2['code']) && $res2['code'] === 0) {
                $order->three_sign = 1;
                $order->save();
                $this->log("-[签署合同2-公章成功]-[合同订单ID：{$order->id}]");
            } else {
                return $this->error($res2['message'] ?? "系统错误，请稍后再试");
            }
            $this->log("-[签署公章返回]", [$res2]);
        }

        $res = $q->shopContract($shop, $order->three_contract_id);

        return $this->success(['url' => $res['result']['pageUrl']]);
    }
}
