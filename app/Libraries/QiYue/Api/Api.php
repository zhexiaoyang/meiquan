<?php

namespace App\Libraries\QiYue\Api;

use App\Models\Shop;
use App\Models\User;

class Api extends Request
{
    /**
     * 连锁店企业认证
     * @param User $user
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/6 10:11 下午
     */
    public function companyauth(User $user)
    {
        $data = [
            "companyName" => $user->company_name,
            "callbackUrl" => "http://psapi.meiquanda.com/api/callback/qiyuesuo/company/auth/status",
            "applicant" => [
                "name" => $user->applicant,
                "contact" => $user->applicant_phone,
                "contactType" => "MOBILE"
            ]
        ];
        return $this->post('/companyauth/pcpage', $data);
    }

    /**
     * 连锁店签署合同
     * @param User $user
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/6 10:11 下午
     */
    public function contract(User $user)
    {
        $data = [
            'contractId' => $user->contract_id,
            "callbackUrl" => "http://psapi.meiquanda.com/api/callback/qiyuesuo/contract/status",
            'user' => [
                'contact' => $user->applicant_phone,
                'contactType' => 'MOBILE'
            ]
        ];
        return $this->post('/v2/contract/pageurl', $data);
    }

    /**
     * 连锁店创建合同草稿
     * @param User $user
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/6 10:12 下午
     */
    public function draft(User $user)
    {
        $data = [
            'send' => true,
            'category' => [
                'id' => '2813328882814423628'
            ],
            'signatories' => [
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '吉林省美全科技有限责任公司'
                ],
                [
                'tenantType' => 'COMPANY',
                'tenantName' => $user->company_name,
                'receiver' => [
                    'contact' => $user->applicant_phone,
                    'contactType' => 'MOBILE'
                ],
            ]
            ],
        ];
        return $this->post('/v2/contract/draft', $data);
    }
}
