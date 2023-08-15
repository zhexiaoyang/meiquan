<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineSelectShop;
use App\Models\Shop;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function shops(Request $request)
    {
        $user = $request->user();
        $shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('second_category', 200001)->where('user_id', $user->id)->get();
        // 选择门店ID
        $select_shop_id = 0;
        if ($select_shops = MedicineSelectShop::where('user_id', $request->user()->id)->first()) {
            $select_shop_id = $select_shops->shop_id;
        }
        // 判断选中门店
        if (!empty($shops)) {
            $shop_ids = $shops->pluck('id')->toArray();
            if (in_array($select_shop_id, $shop_ids)) {
                // 如果选中门店在用户数组里面
                foreach ($shops as $shop) {
                    $shop->checked = $shop->id == $select_shop_id ? 1 : 0;
                }
            } else {
                // 如果选中门店 不在 用户数组里面，默认第一个门店选中
                foreach ($shops as $k => $shop) {
                    $shop->checked = $k == 0 ? 1 : 0;
                }
            }
        }
        foreach ($shops as $shop) {
            if (!$shop->wm_shop_name) {
                $shop->wm_shop_name = $shop->shop_name;
            }
        }
        return $this->success($shops);
    }

    public function index(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$shop = Shop::select('id','user_id')->find($shop_id)) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if ($shop->user_id != $user->id) {
            return $this->error('门店不存在!');
        }
        MedicineSelectShop::updateOrCreate(
            [ 'user_id' => $user->id ],
            [ 'user_id' => $user->id, 'shop_id' => $shop_id ]
        );

        $query = Medicine::select('id','shop_id','name','upc','cover','price','down_price','guidance_price','spec','stock',
            'mt_status','ele_status','online_mt','online_ele','mt_error','ele_error')
            ->where('shop_id', $shop_id);
        // 搜索关键字
        if ($search_key = $request->get('search_key')) {
            if (is_numeric($search_key)) {
                $query->where('upc', $search_key);
            } else {
                $query->where('name', $search_key);
            }
        }
        // TAB搜索 (1 售罄、2 价格异常，3 下架)
        if ($tab = (int) $request->get('tab')) {
            if ($tab === 1) {
                // 售罄
                $query->where('stock', 0);
            } elseif ($tab === 2) {
                // 线上价格异常
                $query->whereColumn('guidance_price', '>=', 'price');
            } elseif ($tab === 3) {
                $query->where(function($query) {
                    // 下架
                    $query->where('online_mt', 0)->orWhere('online_ele', 0);
                });
            }
        }
        // platform(0 全部，1 美团，2 饿了么)
        if ($platform = (int) $request->get('platform')) {
            if ($platform === 1) {
                $query->where('mt_status', 1);
            } elseif ($platform === 2) {
                $query->where('ele_status', 1);
            }
        }
        $medicines = $query->orderByDesc('id')->paginate($request->get('page_size', 10));
        if (!empty($medicines)) {
            foreach ($medicines as $medicine) {
                if ($medicine->mt_status == 1) {
                    $medicine->mt_error = '';
                }
                if ($medicine->ele_status == 1) {
                    $medicine->ele_error = '';
                }
                $medicine->price_error = $medicine->price < $medicine->guidance_price ? 1 : 0;
                // $medicine->down_price_error = $medicine->down_price < $medicine->guidance_price ? 1 : 0;
                $medicine->down_price_error = 0;
            }
        }

        return $this->page($medicines);
    }

    public function statistics(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$shop = Shop::select('id','user_id')->find($shop_id)) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if ($shop->user_id != $user->id) {
            return $this->error('门店不存在!');
        }

        $query = Medicine::select('id','price','down_price','guidance_price','stock','online_mt','online_ele')
            ->where('shop_id', $shop_id);
        if ($search_key = $request->get('search_key')) {
            if (is_numeric($search_key)) {
                $query->where('upc', $search_key);
            } else {
                $query->where('name', $search_key);
            }
        }

        $medicines = $query->get();

        $result = [
            'total' => 0,
            'sell_out' => 0,
            'price_anomaly' => 0,
            'off_shelf' => 0,
        ];
        if (!empty($medicines)) {
            $result['total'] = $medicines->count();
            foreach ($medicines as $medicine) {
                if ($medicine->stock <= 0) {
                    $result['sell_out'] += 1;
                }
                // if ($medicine->price < $medicine->guidance_price || $medicine->down_price < $medicine->guidance_price) {
                if ($medicine->price < $medicine->guidance_price) {
                    $result['price_anomaly'] += 1;
                }
                if ($medicine->online_ele = 0 || $medicine->online_mt = 0) {
                    $result['off_shelf'] += 1;
                }
            }
        }

        return $this->success($result);
    }

    public function update_price(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('药品ID不能为空');
        }
        if (!$down_price = $request->get('down_price')) {
            return $this->error('线下价格不能为空');
        }
        if (!$price = $request->get('price')) {
            return $this->error('线上价格不能为空');
        }
        if (!$guidance_price = $request->get('guidance_price')) {
            return $this->error('成本价不能为空');
        }
        if (!$medicine = Medicine::find($id)) {
            return $this->error('药品不存在');
        }
        $user = $request->user();
        if (!in_array($medicine->shop_id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('药品不存在!');
        }
        $online_update = $price != $medicine->price;
        $update_data = [
            'price' => $price,
            'down_price' => $down_price,
            'guidance_price' => $guidance_price,
        ];
        $medicine->update($update_data);
        // 更新线上价格
        if ($online_update && $price > 0) {
            $shop = null;
            if ($medicine->mt_status == 1 && $price > 0) {
                $shop = Shop::find($medicine->shop_id);
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                }
                if ($meituan !== null) {
                    $params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $medicine->store_id ?: $medicine->upc,
                        'price' => $price,
                        // 'stock' => $stock,
                    ];
                    if ($shop->meituan_bind_platform == 31) {
                        $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $meituan->medicineUpdate($params);
                    // $res = $meituan->medicineUpdate($params);
                    // \Log::info('aaa美团', [$res]);
                }
            }
            if ($medicine->ele_status == 1 && $price > 0) {
                if (!$shop) {
                    $shop = Shop::find($medicine->shop_id);
                }
                $ele = app('ele');
                $params = [
                    'shop_id' => $shop->waimai_ele,
                    'custom_sku_id' => $medicine->store_id ?: $medicine->upc,
                    'sale_price' => (int) ($price * 100),
                    // 'left_num' => $medicine->stock,
                ];
                $ele->skuUpdate($params);
            }
        }

        return $this->success();
    }

    public function update_online_status(Request $request)
    {
        // 美团和饿了么全是上架状态，执行下架。有一个是下架状态，都执行上架操作

        if (!$id = $request->get('id')) {
            return $this->error('药品ID不能为空');
        }
        if (!$medicine = Medicine::find($id)) {
            return $this->error('药品不存在');
        }
        $user = $request->user();
        if (!in_array($medicine->shop_id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('药品不存在!');
        }

        // 更新数组
        $update_data = [];
        $update_status_mt = false;
        $update_status_ele = false;
        if ($medicine->online_mt && $medicine->online_ele) {
            $update_status_mt = true;
            $update_status_ele = true;
            $update_data = [
                'online_mt' => 0,
                'online_ele' => 0,
            ];
        } else {
            if (!$medicine->online_mt) {
                $update_status_mt = true;
                $update_data['online_mt'] = 1;
            }
            if (!$medicine->online_ele) {
                $update_status_ele = true;
                $update_data['online_ele'] = 1;
            }
        }
        if (empty($update_data)) {
            return $this->success();
        }
        $medicine->update($update_data);
        // 更新上下架
        $shop = null;
        if ($update_status_mt && $medicine->mt_status == 1) {
            $shop = Shop::find($medicine->shop_id);
            $meituan = null;
            if ($shop->meituan_bind_platform === 4) {
                $meituan = app('minkang');
            } elseif ($shop->meituan_bind_platform === 31) {
                $meituan = app('meiquan');
            }
            if ($meituan !== null) {
                $params = [
                    'app_poi_code' => $shop->waimai_mt,
                    'app_medicine_code' => $medicine->store_id ?: $medicine->upc,
                    'is_sold_out' => $update_data['online_mt'] == 1 ? 0 : 1
                ];
                if ($shop->meituan_bind_platform == 31) {
                    $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                }
                $meituan->medicineUpdate($params);
            }
        }
        if ($update_status_ele && $medicine->ele_status == 1) {
            if (!$shop) {
                $shop = Shop::find($medicine->shop_id);
            }
            $ele = app('ele');
            $params = [
                'shop_id' => $shop->waimai_ele,
                'custom_sku_id' => $medicine->store_id ?: $medicine->upc,
                'status' => $update_data['online_ele'],
                // 'sale_price' => (int) ($medicine->price * 100),
                // 'left_num' => $medicine->stock,
            ];
            $ele->skuUpdate($params);
        }

        return $this->success();
    }

    public function update_stock(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('药品ID不能为空');
        }
        if (!$stock = (int) $request->get('stock')) {
            return $this->error('库存不能为空');
        }
        if ($stock <= 0) {
            return $this->error('库存不能小于等于0');
        }
        if (!$medicine = Medicine::find($id)) {
            return $this->error('药品不存在');
        }
        $user = $request->user();
        if (!in_array($medicine->shop_id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('药品不存在!');
        }
        $update_data = [
            'stock' => $stock,
        ];
        $medicine->update($update_data);
        // 更新线上价格
        $shop = null;
        if ($medicine->mt_status == 1) {
            $shop = Shop::find($medicine->shop_id);
            $meituan = null;
            if ($shop->meituan_bind_platform === 4) {
                $meituan = app('minkang');
            } elseif ($shop->meituan_bind_platform === 31) {
                $meituan = app('meiquan');
            }
            if ($meituan !== null) {
                $params = [
                    'app_poi_code' => $shop->waimai_mt,
                    'app_medicine_code' => $medicine->store_id ?: $medicine->upc,
                    'stock' => $stock,
                ];
                if ($shop->meituan_bind_platform == 31) {
                    $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                }
                $meituan->medicineUpdate($params);
                // $res = $meituan->medicineUpdate($params);
                // \Log::info('aaa美团', [$res]);
            }
        }
        if ($medicine->ele_status == 1) {
            if (!$shop) {
                $shop = Shop::find($medicine->shop_id);
            }
            $ele = app('ele');
            $params = [
                'shop_id' => $shop->waimai_ele,
                'custom_sku_id' => $medicine->store_id ?: $medicine->upc,
                // 'sale_price' => (int) ($medicine->price * 100),
                'left_num' => $stock,
            ];
            $ele->skuUpdate($params);
        }

        return $this->success();
    }

    public function update_sync(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('药品ID不能为空');
        }
        if (!$medicine = Medicine::find($id)) {
            return $this->error('药品不存在');
        }
        $user = $request->user();
        if (!in_array($medicine->shop_id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('药品不存在!');
        }

        $shop = Shop::find($medicine->shop_id);
        $mt_status = 0;
        $ele_status = 0;
        $mt_res = '美团未同步';
        $ele_res = '饿了么未同步';
        // -------------------------------------------------
        // ---------------------同步美团---------------------
        // -------------------------------------------------
        if ($shop->waimai_mt) {
            if ($medicine->mt_status == 1) {
                // 已经同步过做更新
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                } else {
                    $mt_res = '[美团餐饮]不支持操作';
                }
                if ($meituan !== null) {
                    $params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $medicine->store_id ?: $medicine->upc,
                        'stock' => $medicine->stock,
                        'price' => (float) $medicine->price,
                        'sequence' => $medicine->sequence,
                        'is_sold_out' => $medicine->online_mt == 1 ? 0 : 1,
                    ];
                    if ($shop->meituan_bind_platform == 31) {
                        $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $mt_update_res = $meituan->medicineUpdate($params);
                    if ($mt_update_res['data'] === 'ok') {
                        $mt_res = '美团更新成功';
                    } elseif ($mt_update_res['data'] === 'ng') {
                        $mt_res = $res['error']['msg'] ?? '';
                        $mt_res = substr($mt_res, 0, 200);
                    }
                }
            } else {
                // 未同步过
                // 已经同步过做更新
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                } else {
                    $mt_res = '[美团餐饮]不支持操作';
                }
                if ($meituan !== null) {
                    $categories = MedicineCategory::where('shop_id', $shop->id)->orderBy('pid')->orderBy('sort')->get();
                    $category_key = [];
                    foreach ($categories as $k => $category) {
                        $category_key[$category->id] = $category->name;
                        $category_key[$category->id] = $category->name;
                        if (!$category->mt_id) {
                            if ($category->pid == 0) {
                                $cat_params = [
                                    'app_poi_code' => $shop->waimai_mt,
                                    'category_code' => $category->id,
                                    'category_name' => $category->name,
                                    'sequence' => $category->sort,
                                ];
                            } else {
                                $cat_params = [
                                    'app_poi_code' => $shop->waimai_mt,
                                    'category_name' => $category_key[$category->pid],
                                    'second_category_code' => $category->id,
                                    'second_category_name' => $category->name,
                                    'second_sequence' => $category->sort,
                                ];
                            }
                            if ($shop->meituan_bind_platform == 31) {
                                $cat_params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                            }
                            // $this->log('分类参数', $cat_params);
                            $res = $meituan->medicineCatSave($cat_params);
                            // $this->log('创建分类返回', [$res]);
                            $res_data = $res['data'] ?? '';
                            $error = $res['error']['msg'] ?? '';
                            if (($res_data === 'ok') || (strpos($error, '已经存在') !== false) || (strpos($error, '已存在') !== false)) {
                                $category->mt_id = $category->id;
                                $category->save();
                            }
                        }
                    }
                    $medicine_category = [];
                    if (!empty($medicine->categories)) {
                        foreach ($medicine->categories as $item) {
                            $medicine_category[] = $item->name;
                        }
                    }
                    $medicine_data = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $medicine->store_id ?: $medicine->upc,
                        'upc' => $medicine->upc,
                        'price' => (float) $medicine->price,
                        'stock' => $medicine->stock,
                        'category_name' => implode(',', $medicine_category),
                        'sequence' => $medicine->sequence,
                        'is_sold_out' => $medicine->online_mt == 1 ? 0 : 1,
                    ];
                    if ($shop->meituan_bind_platform == 31) {
                        $medicine_data['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $mt_update_res = $meituan->medicineSave($medicine_data);
                    $error_msg = $mt_update_res['error']['msg'] ?? '';
                    if ($error_msg === '药品分类不存在') {
                        $medicine_data['category_name'] = '未分类';
                        $mt_update_res = $meituan->medicineSave($medicine_data);
                    }
                    if ($mt_update_res['data'] === 'ok') {
                        Medicine::where('id', $id)->update(['mt_status' => 1]);
                        $mt_status = 1;
                        $mt_res = '美团新增成功';
                    } elseif ($mt_update_res['data'] === 'ng') {
                        $error_msg = $res['error']['msg'] ?? '';
                        if ((strstr($error_msg, '已存在') !== false) || (strstr($error_msg, '已经存在') !== false)) {
                            Medicine::where('id', $id)->update(['mt_status' => 1]);
                            $mt_status = 1;
                            $mt_res = '美团新增成功';
                        } else {
                            $mt_res = '美团新增失败';
                            Medicine::where('id', $id)->update(['mt_error' => $res['error']['msg'] ?? '','mt_status' => 2]);
                        }
                    }
                }
            }
        } else {
            $mt_res = '门店未绑定美团';
        }
        // -------------------------------------------------
        // ---------------------饿了么美团---------------------
        // -------------------------------------------------
        if ($shop->waimai_ele) {
            if ($medicine->ele_status == 1) {
                $ele = app('ele');
                $params = [
                    'shop_id' => $shop->waimai_ele,
                    'custom_sku_id' => $medicine->store_id ?: $medicine->upc,
                    'sale_price' => (int) ($medicine->price * 100),
                    'left_num' => $medicine->stock,
                    'status' => $medicine->online_ele,
                ];
                $ele->skuUpdate($params);
                $ele_res = '饿了么更新成功';
            } else {
                $ele = app('ele');
                // 创建药品分类
                $categories = MedicineCategory::where('shop_id', $shop->id)->orderBy('pid')->orderBy('sort')->get();
                $category_key = [];
                foreach ($categories as $k => $category) {
                    $category_key[$category->id] = $category->name;
                    $category_key[$category->id] = $category->name;
                    if (!$category->ele_id) {
                        if ($category->pid == 0) {
                            $cat_params = [
                                'shop_id' => $shop->waimai_ele,
                                'parent_category_id' => 0,
                                'name' => $category->name,
                                'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                            ];
                        } else {
                            $parent = MedicineCategory::find($category->pid);
                            $cat_params = [
                                'shop_id' => $shop->waimai_ele,
                                'parent_category_id' => $parent->ele_id,
                                'name' => $category->name,
                                'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                            ];
                        }
                        \Log::info("药品管理任务饿了么|门店ID:{$shop->id}-分类参数：{$k}", $cat_params);
                        $res = $ele->add_category($cat_params);
                        if (isset($res['body']['data']['category_id'])) {
                            $category->ele_id = $res['body']['data']['category_id'];
                            $category->save();
                        }
                        \Log::info("药品管理任务饿了么|门店ID:{$shop->id}-创建分类返回：{$k}", [$res]);
                    }
                }
                $medicine_category = [];
                if (!empty($medicine->categories)) {
                    foreach ($medicine->categories as $item) {
                        $medicine_category[] = [
                            'category_name' => $item->name
                        ];
                    }
                }
                $medicine_data = [
                    'shop_id' => $shop->waimai_ele,
                    'name' => $medicine->name,
                    'upc' => $medicine->upc,
                    'custom_sku_id' => $medicine->store_id ?: $medicine->upc,
                    'sale_price' => (int) ($medicine->price * 100),
                    'left_num' => $medicine->stock,
                    'category_list' => $medicine_category,
                    'status' => $medicine->online_ele,
                    'base_rec_enable' => true,
                    'photo_rec_enable' => true,
                    'summary_rec_enable' => true,
                    'cat_prop_rec_enable' => true,
                ];
                $res = $ele->add_product($medicine_data);
                if ($res['body']['error'] === 'success') {
                    $ele_res = '饿了么新增成功';
                    $ele_status = 1;
                    Medicine::where('id', $id)->update(['ele_status' => 1]);
                } else {
                    $error_msg = $res['body']['error'] ?? '';
                    if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                        $ele_res = '饿了么新增成功';
                        $ele_status = 1;
                        Medicine::where('id', $id)->update(['ele_status' => 1]);
                    } else {
                        $ele_res = '饿了么新增失败';
                        Medicine::where('id', $id)->update([
                            'ele_error' => $res['body']['error'] ?? '饿了么失败',
                            'ele_status' => 2
                        ]);
                    }
                }
            }
        } else {
            $ele_res = '门店未绑定饿了么';
        }

        $result = [
            'mt_status' => $mt_status,
            'meituan' => $mt_res,
            'ele_status' => $ele_status,
            'ele' => $ele_res,
        ];
        return $this->success($result);
    }
}
