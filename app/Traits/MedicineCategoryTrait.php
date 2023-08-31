<?php

namespace App\Traits;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepot;
use App\Models\MedicineDepotCategory;
use Illuminate\Database\QueryException;

trait MedicineCategoryTrait
{
    public function createCategory($shop, Medicine $medicine, $mt = false, $ele = false)
    {
        // 查询品库中是否有此商品，先确定商品分类
        if ($depot = MedicineDepot::where('upc', $medicine->upc)->first()) {
            \Log::info("药品分类统一管理：品库中有药品");
            // 品库中有商品，获取品库商品分类（末级分类ID）
            $category_ids = \DB::table('wm_depot_medicine_category')->where('medicine_id', $depot->id)->get()->pluck('category_id');
            if (!empty($category_ids)) {
                \Log::info("药品分类统一管理：品库中药品有分类");
                // 获取品库商品分类（末级分类数据）
                $depot_categories = MedicineDepotCategory::whereIn('id', $category_ids)->get();
                if (!empty($depot_categories)) {
                    \Log::info("药品分类统一管理：品库中药品分类-末级分类有数据");
                    foreach ($depot_categories as $depot_category) {
                        // 判断该分类是否已经添加
                        if (!$category = MedicineCategory::where('shop_id', $shop->id)->where('name', $depot_category->name)->first()) {
                            \Log::info("药品分类统一管理：品库中药品分类，该药品没有");
                            // 分类没有添加
                            // 定义父级分类ID，默认为0，一级分类
                            $pid = 0;
                            if ($depot_category->pid != 0) {
                                \Log::info("药品分类统一管理：品库中药品分类是二级分类");
                                // 品库分类是二级分类，查找一级分类
                                if ($depot_category_parent = MedicineDepotCategory::find($depot_category->pid)) {
                                    \Log::info("药品分类统一管理：品库中药品分类是二级分类，找到一级分类了");
                                    // 查看刚刚查找的一级分类是否存在
                                    if (!$category_parent = MedicineCategory::where(['shop_id' => $shop->id, 'name' => $depot_category_parent->name])->first()) {
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
                                            sleep(1);
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

                                        }
                                        \Log::info("药品分类统一管理：品库中药品分类是二级分类，找到一级分类了，给该药品添加一级分类成功");
                                    }
                                    $pid = $category_parent->id;
                                }
                            }
                            try {
                                $category = MedicineCategory::create([
                                    'shop_id' => $shop->id,
                                    'pid' => $pid,
                                    'name' => $depot_category->name,
                                    'sort' => $depot_category->sort,
                                ]);

                                \Log::info("药品分类统一管理：品库中药品分类，创建药品分类成功1");
                            } catch (QueryException $exception) {
                                // \Log::info("导入商品创建分类报错|商品ID：{$model->id}|分类名称：{$category->name}");
                            }
                            \DB::table('wm_medicine_category')->insert(['medicine_id' => $medicine->id, 'category_id' => $category->id]);
                            \Log::info("药品分类统一管理：品库中药品分类，创建药品分类成功2");
                        } else {
                            \DB::table('wm_medicine_category')->insertOrIgnore(['medicine_id' => $medicine->id, 'category_id' => $category->id]);
                            \Log::info("药品分类统一管理：品库中药品分类，该商品有，创建药品分类成功2");
                        }
                    }
                }
            }
        } else {
            // 品库中没有商品，默认分类：暂未分类
            \Log::info("药品分类统一管理：品库中没有药品，创建暂未分类");
            try {
                $category = MedicineCategory::firstOrCreate(
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
            } catch (QueryException $exception) {
                sleep(1);
                $category = MedicineCategory::firstOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'name' => '暂未分类',
                    ],
                    [
                        'shop_id' => $shop->id,
                        'pid' => 0,
                        'name' => '暂未分类',
                        'sort' => 1000,
                    ]);
            }
            if (!\DB::table('wm_medicine_category')->where(['medicine_id' => $medicine->id, 'category_id' => $category->id])->first()) {
                \DB::table('wm_medicine_category')->insert(['medicine_id' => $medicine->id, 'category_id' => $category->id]);
                \Log::info("药品分类统一管理：品库中没有药品，创建暂未分类-写入数据");
            }
            \Log::info("药品分类统一管理：品库中没有药品，创建暂未分类-成功");
        }
        if (!$mt && !$ele) {
            return true;
        }
        // 需要同步美团或者饿了么的
        $result = [
            'mt' => [],
            'ele' => [],
        ];
        $categories = $medicine->categories;
        \Log::info('药品分类统一管理-查找该药品所有末级分类', [$categories]);
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $parent = null;
                $ele_app = null;
                $mt_app = null;
                $mt_bind = $shop->meituan_bind_platform;
                $ele_parent_id = 0;
                $mt_parent_id = 0;
                $mt_parent_name = '';
                if ($category->pid) {
                    \Log::info('药品分类统一管理-查找该药品所有末级分类，查找一级分类');
                    $parent = MedicineCategory::find($category->pid);
                    $update = [];
                    if ($ele) {
                        if (!$parent->ele_id) {
                            \Log::info('药品分类统一管理-查找该药品所有末级分类，一级分类未同步饿了么');
                            $ele_app = app('ele');
                            $cat_params = [
                                'shop_id' => $shop->waimai_ele,
                                'parent_category_id' => 0,
                                'name' => $parent->name,
                                'rank' => 100000 - $parent->sort > 0 ? 100000 - $parent->sort : 1,
                            ];
                            $res = $ele_app->add_category($cat_params);
                            if (isset($res['body']['data']['category_id'])) {
                                \Log::info('药品分类统一管理-查找该药品所有末级分类，一级分类未同步饿了么-同步成功');
                                $update['ele_id'] = $res['body']['data']['category_id'];
                                $ele_parent_id = $res['body']['data']['category_id'];
                            }
                        } else {
                            $ele_parent_id = $parent->ele_id;
                        }
                    }
                    if ($mt && in_array($mt_bind, [4, 31])) {
                        if (!$parent->mt_id) {
                            \Log::info('药品分类统一管理-查找该药品所有末级分类，一级分类未同步美团');
                            if ($mt_bind === 4) {
                                $mt_app = app('minkang');
                            } else {
                                $mt_app = app('meiquan');
                            }
                            $cat_params = [
                                'app_poi_code' => $shop->waimai_mt,
                                'category_code' => $parent->id,
                                'category_name' => $parent->name,
                                'sequence' => $parent->sort,
                            ];
                            if ($mt_bind == 31) {
                                $cat_params['access_token'] = $mt_app->getShopToken($shop->waimai_mt);
                            }
                            $res = $mt_app->medicineCatSave($cat_params);
                            $res_data = $res['data'] ?? '';
                            $error = $res['error']['msg'] ?? '';
                            if (($res_data === 'ok') || (strpos($error, '已经存在') !== false) || (strpos($error, '已存在') !== false)) {
                                \Log::info('药品分类统一管理-查找该药品所有末级分类，一级分类未同步美团-同步成功');
                                $update['mt_id'] = $parent->id;
                                $mt_parent_name = $parent->name;
                                $mt_parent_id = $parent->id;;
                            }
                        } else {
                            $mt_parent_name = $parent->name;
                            $mt_parent_id = $parent->id;
                        }
                    }
                    if (!empty($update)) {
                        MedicineCategory::where('id', $parent->id)->update($update);
                    }
                }
                $update = [];
                if ($ele) {
                    if ($category->ele_id) {
                        \Log::info('药品分类统一管理-查找该药品所有末级分类，已同步饿了么');
                        $result['ele'][] = ['category_name' => $category->name];
                    } else {
                        \Log::info('药品分类统一管理-查找该药品所有末级分类，未同步饿了么');
                        if (!$ele_app) {
                            $ele_app = app('ele');
                        }
                        $cat_params = [
                            'shop_id' => $shop->waimai_ele,
                            'parent_category_id' => $ele_parent_id,
                            'name' => $category->name,
                            'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                        ];
                        $res = $ele_app->add_category($cat_params);
                        if (isset($res['body']['data']['category_id'])) {
                            \Log::info('药品分类统一管理-查找该药品所有末级分类，未同步饿了么-同步成功', [$res]);
                            $result['ele'][] = ['category_name' => $category->name];
                            $update['ele_id'] = $res['body']['data']['category_id'];
                        }
                    }
                }
                if ($mt) {
                    if ($category->mt_id) {
                        \Log::info('药品分类统一管理-查找该药品所有末级分类，已同步美团');
                        $result['mt'][] = $category->name;
                    } else {
                        \Log::info('药品分类统一管理-查找该药品所有末级分类，未同步美团');
                        if (!$mt_app) {
                            if ($mt_bind === 4) {
                                $mt_app = app('minkang');
                            } else {
                                $mt_app = app('meiquan');
                            }
                        }
                        if (!$mt_parent_id) {
                            $cat_params = [
                                'app_poi_code' => $shop->waimai_mt,
                                'category_code' => $category->id,
                                'category_name' => $category->name,
                                'sequence' => $category->sort,
                            ];
                        } else {
                            $cat_params = [
                                'app_poi_code' => $shop->waimai_mt,
                                'category_name' => $mt_parent_name,
                                'second_category_code' => $category->id,
                                'second_category_name' => $category->name,
                                'second_sequence' => $category->sort,
                            ];
                        }
                        if ($mt_bind == 31) {
                            $cat_params['access_token'] = $mt_app->getShopToken($shop->waimai_mt);
                        }
                        $res = $mt_app->medicineCatSave($cat_params);
                        $res_data = $res['data'] ?? '';
                        $error = $res['error']['msg'] ?? '';
                        if (($res_data === 'ok') || (strpos($error, '已经存在') !== false) || (strpos($error, '已存在') !== false)) {
                            \Log::info('药品分类统一管理-查找该药品所有末级分类，未同步美团-同步成功');
                            $result['mt'][] = $category->name;
                            $update['mt_id'] = $category->id;
                        }
                    }
                }
                if (!empty($update)) {
                    MedicineCategory::where('id', $category->id)->update($update);
                }
            }
        }
        return $result;
    }
}
