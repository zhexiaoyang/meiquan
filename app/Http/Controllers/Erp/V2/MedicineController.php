<?php

namespace App\Http\Controllers\Erp\V2;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
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
            $res = $ele->getSkuList($shop->waimai_ele, $page, $pageSize);
            if (isset($res['body']['data']['total']) && !empty($res['body']['data']['list'])) {
                $total = $res['body']['data']['total'];
                foreach ($res['body']['data']['list'] as $v) {
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
        $result = [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list,
        ];
        return $this->success($result);
    }

    public function add(Request $request)
    {
        $mt_id = $request->get('shopIdMeiTuan');
        $ele_id = $request->get('shopIdEle');
        if (!$mt_id && !$ele_id) {
            return $this->error('美团ID和饿了么ID至少需要一个');
        }
        if (!$store_code = $request->get('storeCode')) {
            return $this->error('商家商品编码不能为空');
        }
        if (!$upc = $request->get('upc')) {
            return $this->error('商品条码不能为空');
        }
        if (!$name = $request->get('name')) {
            return $this->error('商品名称不能为空');
        }
        if (($price = $request->get('price')) === null) {
            return $this->error('商品价格不能为空');
        }
        if (!is_numeric($price)) {
            return $this->error('商品价格格式不正确');
        }
        if ($price <= 0) {
            return $this->error('商品价格格式不正确。');
        }
        if (($stock = $request->get('stock')) === null) {
            return $this->error('商品库存不能为空');
        }
        if (!is_numeric($stock)) {
            return $this->error('商品库存格式不正确');
        }
        if ($stock < 0) {
            return $this->error('商品库存格式不正确。');
        }
        if (!$categoryName = $request->get('categoryName')) {
            return $this->error('商品分类不能为空');
        }
        if (!$status = $request->get('status')) {
            return $this->error('商品状态不能为空');
        }
        if (!in_array($status, [1, 2])) {
            return $this->error('商品状态格式不正确');
        }
        if (($sequence = $request->get('sequence')) === null) {
            return $this->error('商品排序不能为空');
        }
        if (!is_numeric($sequence)) {
            return $this->error('商品排序格式不正确');
        }
        if ($sequence < 0) {
            return $this->error('商品排序格式不正确。');
        }
        $mtCode = 701;
        $eleCode = 701;
        $mtMsg = '美团新增商品成功';
        $eleMsg = '饿了么新增商品成功';
        if ($mt_id) {
            if ($shop = Shop::select('id', 'meituan_bind_platform', 'waimai_mt')->where('waimai_mt', $mt_id)->first()) {
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                }
                if ($meituan) {
                    $category_params = [
                        "app_poi_code" => $shop->waimai_mt,
                        "category_name" => $categoryName,
                        "sequence" => 100,
                    ];
                    if ($shop->meituan_bind_platform === 31) {
                        $category_params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $meituan->medicineCatSave($category_params);
                    $params_mt = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $store_code,
                        'upc' => $upc,
                        'price' => $price,
                        'stock' => $stock,
                        'category_name' => $categoryName,
                        'is_sold_out' => 0,
                        'sequence' => $sequence
                    ];
                    if ($shop->meituan_bind_platform === 31) {
                        $params_mt['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $mt_res = $meituan->medicineSave($params_mt);
                    if ($mt_res['data'] === 'ok') {
                        $mtCode = 0;
                        $mtMsg = '美团成功';
                    } else {
                        $mtCode = 702;
                        $mtMsg = $create_log['error']['msg'] ?? '美团失败';
                    }
                }
            } else {
                $mtMsg = '门店不存在';
            }
        }
        if ($ele_id) {
            $ele = app('ele');
            $params_ele = [
                'shop_id' => $ele_id,
                'name' => $name,
                'upc' => $upc,
                'custom_sku_id' => $store_code,
                'sale_price' => (int) ($price * 100),
                'left_num' => $stock,
                'category_name' => $categoryName,
                'status' => 1,
                'base_rec_enable' => true,
                'photo_rec_enable' => true,
                'summary_rec_enable' => true,
                'cat_prop_rec_enable' => true,
            ];
            $res = $ele->add_product($params_ele);
            if ($res['body']['error'] === 'success') {
                $eleCode = 0;
                $eleMsg = '饿了么成功';
            } else {
                $eleCode = 702;
                $eleMsg = $res['body']['error'] ?? '';
            }
        }
        $res = [
            "meituanCode" => $mtCode, //美团新增状态码（0 成功，其它失败）
            "meituanMessage" => $mtMsg, //美团返回信息描述
            "eleCode" => $eleCode, //饿了么新增状态码（0 成功，其它失败）
            "eleMessage" => $eleMsg //饿了么返回信息描述
        ];
        return $this->success($res);
    }

    public function update(Request $request)
    {
        $mt_id = $request->get('shopIdMeiTuan');
        $ele_id = $request->get('shopIdEle');
        if (!$mt_id && !$ele_id) {
            return $this->error('美团ID和饿了么ID至少需要一个');
        }
        if (!$store_code = $request->get('storeCode')) {
            return $this->error('商家商品编码不能为空');
        }
        if (($price = $request->get('price')) === null) {
            return $this->error('商品价格不能为空');
        }
        if (!is_numeric($price)) {
            return $this->error('商品价格格式不正确');
        }
        if ($price <= 0) {
            return $this->error('商品价格格式不正确。');
        }
        if (($stock = $request->get('stock')) === null) {
            return $this->error('商品库存不能为空');
        }
        if (!is_numeric($stock)) {
            return $this->error('商品库存格式不正确');
        }
        if ($stock < 0) {
            return $this->error('商品库存格式不正确。');
        }
        if (!$status = $request->get('status')) {
            return $this->error('商品状态不能为空');
        }
        if (!in_array($status, [1, 2])) {
            return $this->error('商品状态格式不正确');
        }
        if (($sequence = $request->get('sequence')) === null) {
            return $this->error('商品排序不能为空');
        }
        if (!is_numeric($sequence)) {
            return $this->error('商品排序格式不正确');
        }
        if ($sequence < 0) {
            return $this->error('商品排序格式不正确。');
        }
        $mtCode = 701;
        $eleCode = 701;
        $mtMsg = '美团更新商品成功';
        $eleMsg = '饿了么更新商品成功';
        if ($mt_id) {
            if ($shop = Shop::select('id', 'meituan_bind_platform', 'waimai_mt')->where('waimai_mt', $mt_id)->first()) {
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                }
                if ($meituan) {
                    $params_mt = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $store_code,
                        'price' => $price,
                        'stock' => $stock,
                        'is_sold_out' => 0,
                        'sequence' => $sequence
                    ];
                    if ($shop->meituan_bind_platform === 31) {
                        $params_mt['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $mt_res = $meituan->medicineUpdate($params_mt);
                    if ($mt_res['data'] === 'ok') {
                        $mtCode = 0;
                        $mtMsg = '美团成功';
                    } else {
                        $mtCode = 702;
                        $mtMsg = $create_log['error']['msg'] ?? '美团失败';
                    }
                }
            } else {
                $mtMsg = '门店不存在';
            }
        }
        if ($ele_id) {
            $ele = app('ele');
            $params_ele = [
                'shop_id' => $ele_id,
                'custom_sku_id' => $store_code,
                'sale_price' => (int) ($price * 100),
                'left_num' => $stock,
                'status' => 1,
                'base_rec_enable' => true,
                'photo_rec_enable' => true,
                'summary_rec_enable' => true,
                'cat_prop_rec_enable' => true,
            ];
            $res = $ele->skuUpdate($params_ele);
            if ($res['body']['error'] === 'success') {
                $eleCode = 0;
                $eleMsg = '饿了么成功';
            } else {
                $eleCode = 702;
                $eleMsg = $res['body']['error'] ?? '';
            }
        }
        $res = [
            "meituanCode" => $mtCode, //美团新增状态码（0 成功，其它失败）
            "meituanMessage" => $mtMsg, //美团返回信息描述
            "eleCode" => $eleCode, //饿了么新增状态码（0 成功，其它失败）
            "eleMessage" => $eleMsg //饿了么返回信息描述
        ];
        return $this->success($res);
    }

    public function delete(Request $request)
    {
        $mt_id = $request->get('shopIdMeiTuan');
        $ele_id = $request->get('shopIdEle');
        if (!$mt_id && !$ele_id) {
            return $this->error('美团ID和饿了么ID至少需要一个');
        }
        if (!$store_code = $request->get('storeCode')) {
            return $this->error('商家商品编码不能为空');
        }
        $mtCode = 701;
        $eleCode = 701;
        $mtMsg = '美团删除商品成功';
        $eleMsg = '饿了么删除商品成功';
        if ($mt_id) {
            if ($shop = Shop::select('id', 'meituan_bind_platform', 'waimai_mt')->where('waimai_mt', $mt_id)->first()) {
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                }
                if ($meituan) {
                    $params_mt = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $store_code,
                    ];
                    // if ($shop->meituan_bind_platform === 31) {
                    //     $params_mt['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    // }
                    $mt_res = $meituan->medicineDelete($params_mt);
                    if ($mt_res['data'] === 'ok') {
                        $mtCode = 0;
                        $mtMsg = '美团成功';
                    } else {
                        $mtCode = 702;
                        $mtMsg = $create_log['error']['msg'] ?? '美团失败';
                    }
                }
            } else {
                $mtMsg = '门店不存在';
            }
        }
        if ($ele_id) {
            $ele = app('ele');
            $params_ele = [
                'shop_id' => $ele_id,
                'custom_sku_id' => (string) $store_code,
            ];
            $res = $ele->skuDelete($params_ele);
            if ($res['body']['error'] === 'success') {
                $eleCode = 0;
                $eleMsg = '饿了么成功';
            } else {
                $eleCode = 702;
                $eleMsg = $res['body']['error'] ?? '';
            }
        }
        $res = [
            "meituanCode" => $mtCode, //美团新增状态码（0 成功，其它失败）
            "meituanMessage" => $mtMsg, //美团返回信息描述
            "eleCode" => $eleCode, //饿了么新增状态码（0 成功，其它失败）
            "eleMessage" => $eleMsg //饿了么返回信息描述
        ];
        return $this->success($res);
    }
}
