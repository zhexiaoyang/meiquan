<?php

namespace App\Http\Controllers\Erp\V2;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $shopId = $request->get('shopId');
        $type = (int) $request->get('type');
        $page = $request->get('page');
        $pageSize = $request->get('pageSize', 100);

        if (!$shopId) {
            return $this->error('门店ID不能为空', 10010);
        }
        if (!$type) {
            return $this->error('平台不能为空', 10010);
        }
        if (!in_array($type, [1, 2])) {
            return $this->error('平台类型错误', 10010);
        }
        if (!$page) {
            return $this->error('页码不能为空', 10010);
        }
        if ($pageSize < 0 || $pageSize > 100) {
            $pageSize = 100;
        }
        $shop = null;
        if ($type === 1) {
            $shop = Shop::where('waimai_mt', $shopId)->first();
        } else {
            $shop = Shop::where('waimai_ele', $shopId)->first();
        }
        if (!$shop) {
            return $this->error('该门店未绑定', 10010);
        }
        if ($type === 1 && !in_array($shop->meituan_bind_platform, [4, 31])) {
            return $this->error('该门店未绑定!', 10010);
        }
        $total = 0;
        $list = [];
        if ($type === 1) {
            $token = false;
            if ($shop->meituan_bind_platform === 31) {
                $token = true;
                $meituan = app('meiquan');
            } else {
                $meituan = app('minkang');
            }
            $params = [
                'app_poi_code' => $shop->waimai_mt,
                'offset' => $pageSize * ($page - 1),
                'limit' => $pageSize
            ];
            $res = $meituan->medicineList($params, $token);
            if (isset($res['extra_info']['total_count']) && !empty($res['data'])) {
                $total = $res['extra_info']['total_count'];
                foreach ($res['data'] as $v) {
                    $list[] = [
                        "id" => $v['app_medicine_code'],
                        "name" => $v['name'],
                        "storeCode" => $v['app_medicine_code'],
                        "upc" => $v['upc'],
                        "spec" => $v['spec'],
                        "price" => $v['price'],
                        "stock" => $v['stock'],
                        "categoryCode" => $v['category_code'],
                        "categoryName" => $v['category_name'],
                        "sequence" => $v['sequence'],
                    ];
                }
            }
        } else {
            $ele = app('ele');
            $params = [
                'shop_id' => $shop->waimai_ele,
                'page' => $page,
                'pageSize' => $pageSize,
            ];
            $res = $ele->skuList($params);
            if (!isset($res['total']) && !empty($res['list'])) {
                $total = $res['total'];
                foreach ($res['list'] as $v) {
                    $list[] = [
                        "id" => $v['custom_sku_id'],
                        "name" => $v['name'],
                        "storeCode" => $v['custom_sku_id'],
                        "upc" => $v['upc'],
                        "spec" => '',
                        "price" => $v['sale_price'],
                        "stock" => $v['left_num'],
                        "categoryCode" => '',
                        "categoryName" => '',
                        "sequence" => 0,
                    ];
                }
            }
        }
        $res = [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list,
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
