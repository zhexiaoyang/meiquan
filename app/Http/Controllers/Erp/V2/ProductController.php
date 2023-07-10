<?php

namespace App\Http\Controllers\Erp\V2;

use App\Http\Controllers\Controller;
use App\Models\ErpAccessKey;
use App\Models\ErpAccessShop;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepot;
use App\Models\MedicineDepotCategory;
use App\Models\Shop;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function stock(Request $request)
    {
        if (!$access_key = $request->get("access_key")) {
            return $this->error("参数错误：access_key必传", 701);
        }

        if (!$signature = $request->get("signature")) {
            return $this->error("参数错误：signature必传", 701);
        }

        if (!$timestamp = $request->get("timestamp")) {
            return $this->error("参数错误：timestamp必传", 701);
        }

        // if (($timestamp < time() - 300) || ($timestamp > time() + 300)) {
        //     return $this->error("参数错误：timestamp有误", 701);
        // }

        $data = $request->get("data");

        if (empty($data)) {
            return $this->error("参数错误：data不能为空", 701);
        }

        $shop_id_mt = $request->get('shop_id_mt');
        $shop_id_ele = $request->get('shop_id_ele');

        if (!$shop_id_ele && !$shop_id_mt) {
            return $this->error("参数错误：门店ID至少传一个", 701);
        }

        // 判断参数、获取门店
        if (!$access = ErpAccessKey::query()->where("access_key", $access_key)->first()) {
            return $this->error("参数错误：access_key错误", 701);
        }
        // 验证签名
        if (!$this->checkSing($request->only("access_key", "timestamp", "data", "shop_id_mt", "shop_id_ele", "signature"), $access->access_secret)) {
            return $this->error("签名错误", 703);
        }
        // 查找门店
        $shop_where = [];
        if ($shop_id_mt) {
            $shop_where[] = ['waimai_mt', $shop_id_mt];
        }
        if ($shop_id_ele) {
            $shop_where[] = ['waimai_ele', $shop_id_ele];
        }
        if (!$shop = Shop::where($shop_where)->first()) {
            return $this->error("门店不存在。", 701);
        }
        if (!$shop->sync_status) {
            \Log::info("中台关闭数据同步|shop_id:{$shop->id}");
            return $this->error("中台关闭数据同步", 701);
        }
        if (!$key_shop = ErpAccessShop::where(['shop_id' => $shop->id, 'access_id' => $access->id])->first()) {
            return $this->error("门店不存在", 701);
        }
        \Log::info("ERPV2全部参数门店ID{$shop->id}", $request->all());


        // 组合参数
        $mt_binds = [
            'app_poi_code' => $shop_id_mt,
            'medicine_data' => [],
        ];
        $mt_stocks = [
            'app_poi_code' => $shop_id_mt,
            'medicine_data' => [],
        ];
        $stock_data_ele = [];
        foreach ($data as $v) {
            if (isset($v['upc'])) {
                $stock = $v['stock'] ?? 0;
                if ($stock < 0) {
                    $stock = 0;
                }
                $mt_binds['medicine_data'][] = [
                    'upc' => $v['upc'],
                    'app_medicine_code_new' => empty($v['id']) ? $v['upc'] : $v['id'],
                ];
                $mt_stocks['medicine_data'][] = [
                    'app_poi_code' => $shop_id_mt,
                    'app_medicine_code' => empty($v['id']) ? $v['upc'] : $v['id'],
                    'stock' => (int) $stock,
                ];
                $stock_data_ele[] =  $v['upc'] . ':' . (int) $stock;
            }
        }
        // 饿了么同步库存参数
        $ele_stocks['shop_id'] = $shop_id_ele;
        $ele_stocks['upc_stocks'] = implode(';', $stock_data_ele);

        if ($shop->meituan_bind_platform === 4) {
            $meituan = app('minkang');
        } else {
            $meituan = app('meiquan');
            $mt_binds['access_token'] = $meituan->getShopToken($shop_id_mt);
            $mt_stocks['access_token'] = $meituan->getShopToken($shop_id_mt);
        }
        $ele = app('ele');

        // \Log::info("V2ERP全部参数", $request->all());

        // 开始同步
        if ($shop_id_mt) {
            // \Log::info("V2ERP美团库存参数", [$mt_stocks]);
            $mt_binds['medicine_data'] = json_encode($mt_binds['medicine_data']);
            $mt_stocks['medicine_data'] = json_encode($mt_stocks['medicine_data']);
            $mt_binds_res = $meituan->medicineCodeUpdate($mt_binds);
            $mt_stocks_res = $meituan->medicineStock($mt_stocks);
            // \Log::info("V2ERP美团绑定返回", [$mt_binds_res]);
            // \Log::info("V2ERP美团库存返回", [$mt_stocks_res]);
        }
        if ($shop_id_ele) {
            $ele_res = $ele->skuStockUpdate($ele_stocks);
            // \Log::info("V2ERP饿了么库存返回", [$ele_res]);
        }

        foreach ($data as $v) {
            if (isset($v['upc'])) {
                $upc = $v['upc'];
                $price = $v['price'] ?? 0;
                $cost = $v['cost'] ?? 0;
                if (!is_numeric($price)) {
                    $price = 0;
                }
                if (!is_numeric($cost)) {
                    $cost = 0;
                }
                if (Medicine::where('upc', $upc)->where('shop_id', $shop->id)->first()) {
                    $update_data = [
                        'stock' => $v['stock'],
                        // 'price' => 0,
                        'down_price' => $price,
                        'guidance_price' => $cost,
                    ];
                    if (!empty($v['id'])) {
                        $update_data['store_id'] = $v['id'];
                    }
                    Medicine::where('upc', $upc)->where('shop_id', $shop->id)->update($update_data);
                } else {
                    if ($depot = MedicineDepot::where('upc', $upc)->first()) {
                        $depot_category_ids = \DB::table('wm_depot_medicine_category')->where('medicine_id', $depot->id)->get()->pluck('category_id');
                        if (!empty($depot_category_ids)) {
                            // 根据查找的分类ID，查找该商品所有分类
                            $depot_categories = MedicineDepotCategory::whereIn('id', $depot_category_ids)->get();
                            if (!empty($depot_categories)) {
                                foreach ($depot_categories as $depot_category) {
                                    $pid = 0;
                                    if ($depot_category->pid != 0) {
                                        if ($depot_category_parent = MedicineDepotCategory::find($depot_category->pid)) {
                                            try {
                                                $category_parent = MedicineCategory::firstOrCreate(
                                                    [
                                                        'shop_id' => $shop->id,
                                                        'name' => $depot_category_parent->name,
                                                    ],
                                                    [
                                                        'shop_id' => $shop->id,
                                                        'pid' => 0,
                                                        'name' => $depot_category_parent->name,
                                                        'sort' => $depot_category_parent->sort,
                                                    ]
                                                );
                                            } catch (\Exception $exception) {
                                                $category_parent = MedicineCategory::where([
                                                    'shop_id' => $shop->id,
                                                    'name' => $depot_category_parent->name,
                                                ])->first();
                                                \Log::info("创建分类失败(父级)|shop_id:{$shop->id}|name:{$depot_category_parent->name}|重新查询结果：", [$category_parent]);
                                            }
                                            $pid = $category_parent->id;
                                        }
                                    }
                                    try {
                                        $c = MedicineCategory::firstOrCreate(
                                            [
                                                'shop_id' => $shop->id,
                                                'name' => $depot_category->name,
                                            ],
                                            [
                                                'shop_id' => $shop->id,
                                                'pid' => $pid,
                                                'name' => $depot_category->name,
                                                'sort' => $depot_category->sort,
                                            ]
                                        );
                                    } catch (\Exception $exception) {
                                        $c = MedicineCategory::where([
                                            'shop_id' => $shop->id,
                                            'name' => $depot_category->name,
                                        ])->first();
                                        \Log::info("创建分类失败|shop_id:{$shop->id}|name:{$depot_category->name}|重新查询结果：", [$c]);
                                    }
                                }
                            }
                        }
                        $medicine_arr = [
                            'shop_id' => $shop->id,
                            'name' => $depot->name,
                            'upc' => $depot->upc,
                            'cover' => $depot->cover,
                            'brand' => $depot->brand,
                            'spec' => $depot->spec,
                            'price' => 0,
                            'down_price' => $price,
                            'stock' => $stock,
                            'guidance_price' => $cost,
                            'depot_id' => $depot->id,
                        ];
                    } else {
                        $l = strlen($upc);
                        if ($l >= 7 && $l <= 19) {
                            $_depot = MedicineDepot::create([
                                'name' => $v['name'] ?? '',
                                'upc' => $upc
                            ]);
                            \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $_depot->id, 'category_id' => 215]);
                            // 创建-暂未分类
                            $c = MedicineCategory::firstOrCreate(
                                [
                                    'shop_id' => $shop->id,
                                    'name' => '暂未分类',
                                ],
                                [
                                    'shop_id' => $shop->id,
                                    'pid' => 0,
                                    'name' => '暂未分类',
                                    'sort' => 1000,
                                ]
                            );
                            $medicine_arr = [
                                'shop_id' => $shop->id,
                                'name' => $v['name'] ?? '',
                                'upc' => $upc,
                                'brand' => '',
                                'spec' => '',
                                'price' => 0,
                                'down_price' => $price,
                                'stock' => $stock,
                                'guidance_price' => $cost,
                                'depot_id' => $_depot->id,
                            ];
                        }
                    }
                    if (isset($medicine_arr)) {
                        try {
                            $medicine = Medicine::create($medicine_arr);
                            \DB::table('wm_medicine_category')->insert(['medicine_id' => $medicine->id, 'category_id' => $c->id]);
                        } catch (\Exception $exception) {
                            \Log::info("V2添加商品失败", [$exception->getMessage(), $exception->getLine(), $exception->getFile()]);
                        }
                    }
                }
            }
        }

        return $this->success();
    }

    public function checkSing(array $params, string $secret)
    {
        $signature = $params["signature"];
        unset($params["signature"]);
        ksort($params);

        $waitSign = '';
        foreach ($params as $key => $item) {
            if (!empty($item)) {
                if (is_array($item)) {
                    $waitSign .= '&'.$key.'='.json_encode($item, JSON_UNESCAPED_UNICODE);
                } else {
                    $waitSign .= '&'.$key.'='.$item;
                }
            }
        }

        $waitSign = substr($waitSign, 1).$secret;
        // \Log::info("[ERP接口V2]-[校验方法]-签名字符串：{$waitSign}");
        // \Log::info("[ERP接口V2]-[校验方法]-md5字符串：".md5($waitSign));
        \Log::info("VVV:{$waitSign}");

        return $signature === md5($waitSign);
    }
}
