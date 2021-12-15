<?php

namespace App\Libraries\TaoZi\Api;

use App\Models\Shop;
use App\Models\User;
use App\Models\WmPrescription;

class Api extends Request
{
    public function order($page = 1, $size = 100, $date = null)
    {
        $date = $date ?? date("Y-m-d", time() - 86400 * 3);

        $data = [
            'pageIndex' => $page,
            'pageSize' => $size,
            'date' => $date,
        ];

        return $this->post('api/opendata/v1/meituan/rpOrderList', $data);
    }

    public function create_order(User $user, Shop $shop, WmPrescription $prescription)
    {
        $data = [
            "personInfo" => [
                "thirdTypeID" => 0,
                "thirdUniqueID" => $user->id,
                "personName" => $shop->contact_name,
                "phone" => $shop->contact_phone,
            ],
            "orgInfo" => [
                "orgTypeID" => 9,
                "pharmacyLevelID" => 1,
                "thirdUniqueID" => (string) $shop->id,
                "orgName" => $shop->shop_name,
                "province" => $shop->province,
                "city" => $shop->city,
                "area" => $shop->district
            ]
        ];

        // $data = [
        //     "thirdOrgID" => $prescription->outOrderID,
        //     "thirdOrgName" => '美全科技',
        //     "personInfo" => [
        //         "thirdTypeID" => 0,
        //         "thirdUniqueID" => $user->id,
        //         "personName" => $user->phone,
        //         "phone" => $user->phone,
        //     ],
        //     "orgInfo" => [
        //         "orgTypeID" => 9,
        //         "pharmacyLevelID" => 1,
        //         "thirdUniqueID" => $shop->id,
        //         "orgName" => $shop->shop_name,
        //         "city" => $shop->city,
        //     ]
        // ];

        return $this->post2('api/peachLogin/pharmacy/thirdSilenceLogin', $data);
    }
}
