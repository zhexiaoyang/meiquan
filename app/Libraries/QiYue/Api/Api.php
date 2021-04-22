<?php

namespace App\Libraries\QiYue\Api;

use App\Models\OnlineShop;
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
            // "callbackUrl" => "http://psapi.meiquanda.com/api/callback/qiyuesuo/contract/status",
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
     * @param array $params
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/6 10:12 下午
     */
    public function draft(User $user, array $params)
    {
        $data = [
            'send' => true,
            'category' => [
                'id' => '2821037489974678141'
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
                    'templateParams' => [
                        ''
                    ]
                ]
            ],
        ];
        return $this->post('/v2/contract/draft', $data);
    }

    /**
     * 门店认证
     * @param OnlineShop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 9:25 上午
     */
    public function shopAuth(OnlineShop $shop)
    {
        $data = [
            "companyName" => $shop->company_name,
            "callbackUrl" => "http://psapi.meiquanda.com/api/callback/qiyuesuo/shop/auth/status",
            "applicant" => [
                "name" => $shop->applicant,
                "contact" => $shop->applicant_phone,
                "contactType" => "MOBILE"
            ]
        ];
        return $this->post('/companyauth/pcpage', $data);
    }

    /**
     *
     * @param OnlineShop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 9:25 上午
     */
    public function shopDraft(OnlineShop $shop)
    {
        $data = [
            'send' => true,
            'category' => [
                'id' => '2822634020993504105'
            ],
            'signatories' => [
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '吉林省美全科技有限责任公司'
                ],
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => $shop->company_name,
                    'receiver' => [
                        'contact' => $shop->applicant_phone,
                        'contactType' => 'MOBILE'
                    ]
                ]
            ],
            'templateParams' => [
                [
                    "name" => "甲方公司名称",
                    "value" => $shop->company_name
                ],
                [
                    "name" => "甲方开户名称",
                    "value" => $shop->bank_user
                ],
                [
                    "name" => "甲方回款账号",
                    "value" => $shop->account_no
                ],
                [
                    "name" => "甲方开户银行",
                    "value" => $shop->bank_name
                ]
            ]
        ];
        return $this->post('/v2/contract/draft', $data);
    }

    /**
     * 门店签署
     * @param OnlineShop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 3:21 下午
     */
    public function shopContract(OnlineShop $shop)
    {
        $data = [
            'contractId' => $shop->contract_id,
            // "callbackUrl" => "http://psapi.meiquanda.com/api/callback/qiyuesuo/contract/status",
            'user' => [
                'contact' => $shop->applicant_phone,
                'contactType' => 'MOBILE'
            ]
        ];
        return $this->post('/v2/contract/pageurl', $data);
    }
}
