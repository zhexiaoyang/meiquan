<?php

namespace App\Http\Controllers;

use App\Libraries\QiYue\QiYue;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function auth(Request $request)
    {
        if (!$company_name = $request->get("company_name")) {
            return $this->error("公司名称不能为空");
        }
        if (!$applicant_name = $request->get("applicant_name")) {
            return $this->error("认证人不能为空");
        }
        if (!$applicant_phone = $request->get("applicant_phone")) {
            return $this->error("认证人电话不能为空");
        }

        $user = $request->user();
        if ($user->contract === 0) {
            $user->company_name = $company_name;
            $user->applicant_name = $applicant_name;
            $user->applicant_phone = $applicant_phone;
            $user->save();
        }

        $config = config('qiyuesuo');
        $q = new QiYue($config);
        $data = [
            "companyName" => $user->company_name,
            "applicant" => [
                "name" => $user->applicant_name,
                "contact" => $user->applicant_phone,
                "contactType" => "MOBILE"
            ]
        ];
        $res = $q->companyauth($data);

        if (!isset($res['code']) || ($res['code'] !== 0)) {
            return $this->error("认证失败，请稍后再试");
        }

        return $this->success($res);
    }
}
