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
                    'tenantName' => '杭州美全健康科技有限公司'
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
     * 合同草稿
     * 运营合同
     * @param OnlineShop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/20 9:25 上午
     */
    public function shopDraft(OnlineShop $shop)
    {
        $data = [
            'send' => true,
            'tenantName' => '杭州美全健康科技有限公司',
            'category' => [
                'id' => '3075402575948882166'
            ],
            'signatories' => [
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '杭州美全健康科技有限公司'
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
                // [
                //     "name" => "甲方开户名称",
                //     "value" => $shop->bank_user
                // ],
                // [
                //     "name" => "甲方回款账号",
                //     "value" => $shop->account_no
                // ],
                // [
                //     "name" => "甲方开户银行",
                //     "value" => $shop->bank_name
                // ]
            ]
        ];
        return $this->post('/v2/contract/draft', $data);
    }

    /**
     * 合同草稿
     * 美团-桃子处方
     * @data 2021/12/10 3:13 下午
     */
    public function shopDraftTaozi(OnlineShop $shop, $type = 1)
    {
        // $type = 1;
        // $ids = ['', '2907910074598953069', '2907917307005113105', '2907925343920722421'];
        $ids = ['', '3075397622647825396', '3075396328235205253', '3075393744749531689'];
        $data = [
            'send' => true,
            'tenantName' => '杭州美全健康科技有限公司',
            'category' => [
                'id' => $ids[$type]
            ],
            'signatories' => [
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '杭州美全健康科技有限公司'
                ],
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '四川桃子健康科技股份有限公司',
                    'receiver' => [
                        'name' => '赖婧',
                        'contact' => '18581866171',
                        'contactType' => 'MOBILE'
                    ]
                ],
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => $shop->company_name,
                    'receiver' => [
                        'name' => $shop->applicant,
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
                    "name" => "甲方公司地址",
                    "value" => $shop->address
                ],
                [
                    "name" => "甲方联系人",
                    "value" => $shop->applicant
                ],
                [
                    "name" => "甲方联系电话",
                    "value" => $shop->applicant_phone
                ]
            ]
        ];
        return $this->post('/v2/contract/draft', $data);
    }

    /**
     * VIP合同
     * @param OnlineShop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2022/3/1 8:52 下午
     */
    public function shopDraftVip(OnlineShop $shop)
    {
        $data = [
            'send' => true,
            'tenantName' => '杭州美全健康科技有限公司',
            'category' => [
                // 'id' => '2936257351293943938',
                'id' => '3075391582699004799',
            ],
            'signatories' => [
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '杭州美全健康科技有限公司'
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
                // [
                //     "name" => "甲方开户名称",
                //     "value" => $shop->bank_user
                // ],
                // [
                //     "name" => "甲方回款账号",
                //     "value" => $shop->account_no
                // ],
                // [
                //     "name" => "甲方开户银行",
                //     "value" => $shop->bank_name
                // ]
            ]
        ];
        return $this->post('/v2/contract/draft', $data);
    }


    /**
     * ERP对接补充协议
     * @param OnlineShop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/1 8:52 下午
     */
    public function shopDraftErp(OnlineShop $shop)
    {
        $data = [
            'send' => true,
            'tenantName' => '杭州美全健康科技有限公司',
            'category' => [
                // 'id' => '2980478614300041265',
                'id' => '3075388870649447009',
            ],
            'signatories' => [
                [
                    'tenantType' => 'COMPANY',
                    'tenantName' => '杭州美全健康科技有限公司'
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
                    "name" => "甲方联系人",
                    "value" => $shop->applicant
                ]
            ]
        ];
        return $this->post('/v2/contract/draft', $data);
    }

    /**
     * 门店签署-获取签署链接
     * @param OnlineShop $shop
     * @param $contract_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/12/10 3:23 下午
     */
    public function shopContract(OnlineShop $shop, $contract_id)
    {
        $data = [
            'contractId' => $contract_id,
            // "callbackUrl" => "http://psapi.meiquanda.com/api/callback/qiyuesuo/contract/status",
            'user' => [
                'contact' => $shop->applicant_phone,
                'contactType' => 'MOBILE'
            ]
        ];
        return $this->post('/v2/contract/pageurl', $data);
    }

    /**
     * 签署公章-桃子医院盖章
     * @param $contract_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/12/10 3:23 下午
     */
    public function companysignTaozi($contract_id, $type = 1)
    {
        // $type = 1;
        // sealId 公章ID 桃子的
        $data = [
            'contractId' => $contract_id,
            'tenantName' => '四川桃子健康科技股份有限公司',
            // 'tenantName' => '成都双流桃子互联网医院有限公司',
            'sealId' => '3075708460902514957',
        ];
        return $this->post('/v2/contract/companysign', $data);
    }

    public function download($id)
    {
        return $this->post('/v2/document/download', ['documentId' => $id]);
    }
}
