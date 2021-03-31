<?php

namespace App\Libraries\QiYue\Api;

use App\Models\Shop;

class Api extends Request
{
    public function companyauth(array $data)
    {
        return $this->post('/companyauth/pcpage', $data);
    }

    public function contract(array $data = [])
    {
        $data = [
            'contractId' => '2814368619624927578',
            'user' => [
                'contact' => '15578995421',
                'contactType' => 'MOBILE'
            ]
        ];
        return $this->post('/v2/contract/pageurl', $data);
    }

    public function addbytemplate(array $data = [])
    {
        $data = [
            'contractId' => '1001',
            'title' => '测试合同',
            'templateId' => '2813999920585904666',
        ];
        return $this->post('/v2/document/addbytemplate', $data);
    }

    public function draft(array $data = [])
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
                'tenantName' => '美全达',
                'receiver' => [
                    'contact' => '15578995421',
                    'contactType' => 'MOBILE'
                ],
            ]
            ],
        ];
        return $this->post('/v2/contract/draft', $data);
    }
}
