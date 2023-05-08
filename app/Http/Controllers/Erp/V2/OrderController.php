<?php

namespace App\Http\Controllers\Erp\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function info(Request $request)
    {
        $res = [
            "orderId" => "800523467812378",
            "isPrescription" => 0,
            "recipientName" => "测试（先生）",
            "recipientPhone" => "13812345678_1236",
            "recipientAddress" => "色金拉 (色金拉)",
            "shippingFee" => 4,
            "total" => 4,
            "originalPrice" => 4,
            "caution" => "【如遇缺货】：缺货时电话与我沟通收货人隐私号 18689114387_3473,手机号 185****2033",
            "status" => 4,
            "ctime" => "1558955579",
            "utime" => "1558955579",
            "latitude" => 29.774491,
            "longitude" => 95.369272,
            "daySeq" => 12,
            "detail" => [
                [
                    "storeCode" => "code_24367",
                    "name" => "温度计",
                    "upc" => "6923995006311",
                    "quantity" => 2,
                    "price" => 12.5,
                    "unit" => "售卖单位",
                    "spec" => "规格",
                ]
            ]
        ];
        return $this->success($res);
    }

    public function orderStatus(Request $request)
    {
        $res = [
            "status" => 4
        ];
        return $this->success($res);
    }
}
