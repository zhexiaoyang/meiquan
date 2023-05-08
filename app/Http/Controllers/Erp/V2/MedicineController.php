<?php

namespace App\Http\Controllers\Erp\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $res = [
            'total' => 1,
            'page' => 1,
            'pageSize' => 10,
            'list' => [
                [
                    "id" => "7812638", //美团（饿了么）ID
                    "name" => "布洛芬缓释片", //药品名称
                    "storeCode" => "code_123",
                    "upc" => "60392038392302", //药品条码
                    "spec" => "0.5g*10粒", //规格
                    "price" => 13.5, //售价
                    "stock" => 10, //库存
                    "categoryCode" => "901600",
                    "categoryName" => "退烧止痛",
                    "sequence" => 10 //药品在所属末级分类下的排序序号，同一分类下药品排序序号数字越小，前端排位越靠前。
                ]
            ]
        ];
        return $this->success($res);
    }

    public function add(Request $request)
    {
        $res = [
            "meituanCode" => 0, //美团新增状态码（0 成功，其它失败）
            "meituanMessage" => "美团新增商品成功", //美团返回信息描述
            "eleCode" => 701, //饿了么新增状态码（0 成功，其它失败）
            "eleMessage" => "饿了么新增失败：分类不存在" //饿了么返回信息描述
        ];
        return $this->success($res);
    }

    public function update()
    {
        $res = [
            "meituanCode" => 0, //美团新增状态码（0 成功，其它失败）
            "meituanMessage" => "美团修改商品成功", //美团返回信息描述
            "eleCode" => 701, //饿了么新增状态码（0 成功，其它失败）
            "eleMessage" => "饿了么修改失败：分类不存在" //饿了么返回信息描述
        ];

        return $this->success($res);
    }

    public function delete()
    {
        $res = [
            "meituanCode" => 0, //美团新增状态码（0 成功，其它失败）
            "meituanMessage" => "美团删除商品成功", //美团返回信息描述
            "eleCode" => 701, //饿了么新增状态码（0 成功，其它失败）
            "eleMessage" => "饿了么删除失败：药品不存在" //饿了么返回信息描述
        ];

        return $this->success($res);
    }
}
