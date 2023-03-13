<?php

namespace App\Http\Controllers;

use App\Exports\WmMedicineExport;
use App\Imports\MedicineImport;
use App\Imports\MedicineUpdateImport;
use App\Jobs\MedicineSyncJob;
use App\Jobs\MedicineSyncMeiTuanItemJob;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepot;
use App\Models\MedicineSyncLog;
use App\Models\Shop;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MedicineController extends Controller
{
    public function shops(Request $request)
    {
        $query = Shop::select('id', 'shop_name')->where('second_category', 200001)->where('status', '>=', 0);
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            // \Log::info("没有全部门店权限");
            $query->whereIn('id', $request->user()->shops()->pluck('id')->toArray());
        }
        if ($name = $request->get('name')) {
            $shops = $query->where('shop_name', 'like', "%{$name}%")->orderBy('id')->limit(30)->get();
        } else {
            $shops = $query->orderBy('id')->limit(15)->get();
        }

        return $this->success($shops);
    }

    public function sync_log(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->success();
        }
        $logs = MedicineSyncLog::where('shop_id', $shop_id)->orderByDesc('id')->limit(1)->get();

        return $this->success($logs);
    }

    public function product(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!Shop::find($shop_id)) {
            return $this->error('门店错误.');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店错误');
            }
        }

        $query = Medicine::with(['categories' => function ($query) {
            $query->select('id', 'name');
        }])->where('shop_id', $shop_id);

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($upc = $request->get('upc')) {
            $query->where('upc', $upc);
        }
        $mt = $request->get('mt');
        if (!is_null($mt) && $mt !== '') {
            $query->where('mt_status', $mt);
        }
        $ele = $request->get('ele');
        if (!is_null($ele) && $ele !== '') {
            $query->where('ele_status', $ele);
        }
        if ($id = $request->get('id')) {
            $query->where('id', $id);
        }
        if ($category_id = $request->get('category_id')) {
            $query->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }
        if ($excep = $request->get('exception')) {
            $excep = intval($excep);
            if ($excep === 2) {
                $query->whereColumn('guidance_price', '>=', 'price');
            }
        }

        $data =$query->paginate($request->get('page_size', 10));

        return $this->page($data, [],'data');
    }

    public function import(Request $request, MedicineImport $import)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        $import->shop_id = $shop_id;
        Excel::import($import, $request->file('file'));
        return $this->success();
    }

    public function updateImport(Request $request, MedicineUpdateImport $import)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        $import->shop_id = $shop_id;
        Excel::import($import, $request->file('file'));
        return $this->success();
    }

    public function sync(Request $request)
    {
        $product_ids = $request->get('product_id', []);
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$platform = $request->get('platform')) {
            return $this->error('请选择同步平台');
        }
        if (!in_array($platform, [1,2])) {
            return $this->error('同步平台选择错误');
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在，请核对');
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店不存在');
            }
        }

        if ($platform === 1 && !$shop->waimai_mt) {
            return $this->error('该门店没有绑定美团，不能同步商品');
        }

        if ($platform === 2 && !$shop->waimai_ele) {
            return $this->error('该门店没有绑定饿了么，不能同步商品');
        }

        if (MedicineSyncLog::where('shop_id', $shop_id)->where('status', 1)->where('created_at', '>', date("Y-m-d H:i:s", time() - 610))->first()) {
            \Log::info('药品管理任务控制器|已存在进行中任务停止任务');
            return $this->error('已有进行中的任务，请等待');
        }

        if ($platform === 2) {
            // 饿了么走这个JOB
            MedicineSyncJob::dispatch($shop, $platform)->onQueue('medicine');
            return $this->success();
        }

        // ***************************** 美团逻辑·开始 *****************************
        // 判断美团绑定
        if ($shop->meituan_bind_platform === 4) {
            $meituan = app('minkang');
        } elseif ($shop->meituan_bind_platform === 31) {
            $meituan = app('meiquan');
        } else {
            return $this->error('该门店没有绑定');
        }

        // MedicineSyncJob::dispatch($shop, $platform);
        $log_id = uniqid();

        // 创建药品分类
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
        $medicine_list_query = Medicine::with('categories')->where('shop_id', $shop->id)
            ->whereIn('mt_status', [0, 2]);
        if (!empty($product_ids)) {
            $medicine_list_query->whereIn('id', $product_ids);
        }
        $medicine_list = $medicine_list_query->limit(5000)->get();
        // 添加日志
        $log = MedicineSyncLog::create([
            'shop_id' => $shop->id,
            'platform' => $platform,
            'log_id' => $log_id,
            'total' => $medicine_list->count(),
            'success' => 0,
            'fail' => 0,
            'error' => 0,
        ]);
        if (!empty($medicine_list)) {
            foreach ($medicine_list as $medicine) {
                $medicine_category = [];
                if (!empty($medicine->categories)) {
                    foreach ($medicine->categories as $item) {
                        $medicine_category[] = $item->name;
                    }
                }
                $medicine_data = [
                    'app_poi_code' => $shop->waimai_mt,
                    'app_medicine_code' => $medicine->upc,
                    'upc' => $medicine->upc,
                    'price' => (float) $medicine->price,
                    'stock' => $medicine->stock,
                    'category_name' => implode(',', $medicine_category),
                    'sequence' => $medicine->sequence,
                    'is_sold_out' => 0,
                ];
                if ($shop->meituan_bind_platform == 31) {
                    $medicine_data['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                }
                MedicineSyncMeiTuanItemJob::dispatch($log->id, $medicine_data, $shop->meituan_bind_platform, $shop, $medicine->id, $medicine->depot_id, $medicine->name, $medicine->upc)
                ->onQueue('medicine');

            }
        }
        // ***************************** 美团逻辑·结束 *****************************

        return $this->success();
    }

    // public function sync(Request $request)
    // {
    //     if (!$shop_id = $request->get('shop_id')) {
    //         return $this->error('请选择门店');
    //     }
    //     if (!$platform = $request->get('platform')) {
    //         return $this->error('请选择同步平台');
    //     }
    //     if (!in_array($platform, [1,2])) {
    //         return $this->error('同步平台选择错误');
    //     }
    //
    //     if (!$shop = Shop::find($shop_id)) {
    //         return $this->error('门店不存在，请核对');
    //     }
    //
    //     if (!$request->user()->hasPermissionTo('currency_shop_all')) {
    //         if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
    //             return $this->error('门店不存在');
    //         }
    //     }
    //
    //     if ($platform === 1 && !$shop->waimai_mt) {
    //         return $this->error('该门店没有绑定美团，不能同步商品');
    //     }
    //
    //     if ($platform === 2 && !$shop->waimai_ele) {
    //         return $this->error('该门店没有绑定饿了么，不能同步商品');
    //     }
    //
    //     if (MedicineSyncLog::where('shop_id', $shop_id)->where('status', 1)->where('created_at', '>', date("Y-m-d H:i:s", time() - 610))->first()) {
    //         \Log::info('药品管理任务控制器|已存在进行中任务停止任务');
    //         return $this->error('已有进行中的任务，请等待');
    //     }
    //
    //     MedicineSyncJob::dispatch($shop, $platform);
    //
    //     return $this->success();
    // }

    /**
     * 导出
     * @param Request $request
     * @param WmMedicineExport $export
     * @return WmMedicineExport|mixed
     * @author zhangzhen
     * @data 2022/11/22 11:24 上午
     */
    public function export_medicine(Request $request, WmMedicineExport $export)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        $type = $request->get('type');
        if ($type != 1 && $type != 2) {
            $type = 1;
        }
        return $export->withRequest($shop_id, $type);
    }

    /**
     * 更新
     * @param Medicine $medicine
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/11/22 9:32 下午
     */
    public function update(Medicine $medicine, Request $request)
    {
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($medicine->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('商品错误');
            }
        }
        $data = [];
        if (!$name = $request->get('name')) {
            return $this->error('名称不能为空');
        }
        $stock = (int) $request->get('stock');
        $price = (float) $request->get('price');
        $guidance_price = (float) $request->get('guidance_price');

        if ($price <= 0) {
            return $this->error('销售价不能为0');
        }
        $data['name'] = $name;
        $data['stock'] = $stock;
        $data['price'] = $price;
        $data['guidance_price'] = $guidance_price;

        $medicine->update($data);

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
                    'app_medicine_code' => $medicine->upc,
                    'price' => $price,
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
        if ($medicine->ele_status == 1 && $price > 0) {
            if (!$shop) {
                $shop = Shop::find($medicine->shop_id);
            }
            $ele = app('ele');
            $params = [
                'shop_id' => $shop->waimai_ele,
                'custom_sku_id' => $medicine->upc,
                'sale_price' => (int) ($medicine->price * 100),
                'left_num' => $medicine->stock,
            ];
            $ele->skuUpdate($params);
            // $res = $ele->skuUpdate($params);
            // \Log::info('aaa饿了么', [$res]);
        }

        // if ($medicine->mt_status == 2) {
        //     if (!$shop) {
        //         $shop = Shop::find($medicine->shop_id);
        //     }
            // $ele = app('ele');
            // if ($meituan !== null) {
            //     $params = [
            //         'app_poi_code' => $shop->waimai_mt,
            //         'app_medicine_code' => $medicine->upc,
            //         'price' => $price,
            //         'stock' => $stock,
            //     ];
            //     if ($shop->meituan_bind_platform == 31) {
            //         $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
            //     }
            //     $res = $meituan->medicineUpdate($params);
            // }
        // }
        return $this->success();
    }

    public function clear(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店错误');
            }
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        if (!$shop->waimai_mt) {
            return $this->error('该门店没有绑定美团，无法清空商品');
        }
        $meituan = null;
        if ($shop->meituan_bind_platform === 4) {
            $meituan = app('minkang');
        } elseif ($shop->meituan_bind_platform === 31) {
            $meituan = app('meiquan');
        }
        if (!$meituan) {
            return $this->error('门店绑定异常');
        }
        $params = [
            'app_poi_code' => $shop->waimai_mt,
        ];
        if ($shop->meituan_bind_platform == 31) {
            $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
        }
        $res = $meituan->shangouDeleteAll($params);
        \Log::info("清空门店商品返回", [$res]);
        $status = $res['data'] ?? '';
        $error_msg = $res['error']['msg'] ?? '清空失败';
        if ($status !== 'ok' && $error_msg != '门店内不存在任何分类') {
            // if ($error_msg == '门店内不存在任何分类') {
            //     Medicine::where('shop_id', $shop->id)->delete();
            //     MedicineCategory::where('shop_id', $shop->id)->delete();
            // }
            return $this->error($error_msg, 422);
        }

        Medicine::where('shop_id', $shop->id)->delete();
        MedicineCategory::where('shop_id', $shop->id)->delete();

        return $this->success();
    }

    public function clear_middle(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店错误');
            }
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        Medicine::where('shop_id', $shop->id)->delete();
        MedicineCategory::where('shop_id', $shop->id)->delete();

        return $this->success();
    }

    public function info_by_upc(Request $request)
    {
        if (!$upc = $request->get('upc')) {
            return $this->error('药品编码不存在');
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if ($medicine = Medicine::where('upc', $upc)->where('shop_id', $shop_id)->first()) {
            return $this->error('药品管理中无此药品');
        }
        return $this->success();
    }

    public function statistics_status(Request $request)
    {
        $data = [
            'total' => 0,
            'price_exception' => 0,
            'mt1' => 0,
            'mt2' => 0,
            'mt3' => 0,
            'ele1' => 0,
            'ele2' => 0,
            'ele3' => 0,
        ];

        if ($shop_id = $request->get('shop_id')) {
            $medicines = Medicine::where('shop_id', $shop_id)->get();
            if (!empty($medicines)) {
                foreach ($medicines as $medicine) {
                    $data['total']++;
                    if ($medicine->price <= $medicine->guidance_price) {
                        $data['price_exception']++;
                    }
                    if ($medicine->mt_status === 1) {
                        $data['mt1']++;
                    } else if ($medicine->mt_status === 0) {
                        $data['mt2']++;
                    } else if ($medicine->mt_status === 2) {
                        $data['mt3']++;
                    }
                    if ($medicine->ele_status === 1) {
                        $data['ele1']++;
                    } else if ($medicine->ele_status === 0) {
                        $data['ele2']++;
                    } else if ($medicine->ele_status === 2) {
                        $data['ele3']++;
                    }
                }
            }
        }

        return $this->success($data);
    }

    public function depot_index(Request $request)
    {
        $query = MedicineDepot::select('id','name','cover','price','upc','sequence');

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($upc = $request->get('upc')) {
            $query->where('upc', $upc);
        }

        $data =$query->paginate($request->get('page_size', 10));

        return $this->page($data, [],'data');
    }

    public function depot_add(Request $request)
    {
        $message = '';
        if (!$depot_id = $request->get('id')) {
            return $this->error('品库中无此商品');
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if (!$price = $request->get('price')) {
            return $this->error('价格不能为空');
        }
        if (!$stock = $request->get('stock')) {
            return $this->error('库存不能为空');
        }
        $cost = (int) $request->get('cost', 0);
        if (!$depot = MedicineDepot::find($depot_id)) {
            return $this->error('品库中无此商品!');
        }
        if (Medicine::where('shop_id', $shop_id)->where('upc', $depot->upc)->first()) {
            return $this->error('该药品已存在');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在!');
        }

        $medicine_arr = [
            'shop_id' => $shop->id,
            'name' => $depot->name,
            'upc' => $depot->upc,
            'cover' => $depot->cover,
            'brand' => $depot->brand,
            'spec' => $depot->spec,
            'price' => $price,
            'stock' => $stock,
            'guidance_price' => $cost,
            'depot_id' => $depot->id,
        ];

        $medicine = Medicine::create($medicine_arr);
        $message .= '商品添加成功。';
        // return $this->success($medicine->categories);

        if ($request->get('mt')) {
            if (!$shop->waimai_mt) {
                $message .= '美团未绑定，无法同步。';
            } else {
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                } else {
                    $meituan = null;
                }

                if ($meituan) {
                    $categories = $medicine->categories;
                    $cat_arr = [];
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            $cat_arr[] = $category->name;
                            if ($category->pid != 0) {
                                $f_cat = MedicineCategory::find($category->pid);
                                $cat_params = [
                                    'app_poi_code' => $shop->waimai_mt,
                                    'category_code' => $f_cat->id,
                                    'category_name' => $f_cat->name,
                                    'sequence' => $f_cat->sort,
                                ];
                                if ($shop->meituan_bind_platform == 31) {
                                    $cat_params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                                }
                                $res = $meituan->medicineCatSave($cat_params);
                                \Log::info("品库添加商品-美团添加一级分类", [$cat_params, $res]);
                                unset($cat_params);
                                $cat_params = [
                                    'app_poi_code' => $shop->waimai_mt,
                                    'category_name' => $f_cat->name,
                                    'second_category_code' => $category->id,
                                    'second_category_name' => $category->name,
                                    'second_sequence' => $category->sort,
                                ];
                                if ($shop->meituan_bind_platform == 31) {
                                    $cat_params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                                }
                                $res = $meituan->medicineCatSave($cat_params);
                                \Log::info("品库添加商品-美团添加二级分类", [$cat_params, $res]);
                            } else {
                                $cat_params = [
                                    'app_poi_code' => $shop->waimai_mt,
                                    'category_code' => $category->id,
                                    'category_name' => $category->name,
                                    'sequence' => $category->sort,
                                ];
                                if ($shop->meituan_bind_platform == 31) {
                                    $cat_params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                                }
                                $res = $meituan->medicineCatSave($cat_params);
                                \Log::info("品库添加商品-美团添加一级分类", [$cat_params, $res]);
                            }
                        }
                    }
                    $medicine_data = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $medicine->upc,
                        'upc' => $medicine->upc,
                        'price' => (float) $medicine->price,
                        'stock' => $medicine->stock,
                        'category_name' => implode(',', $cat_arr),
                        'sequence' => $medicine->sequence,
                        'is_sold_out' => 0,
                    ];
                    if ($shop->meituan_bind_platform == 31) {
                        $medicine_data['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $res = $meituan->medicineSave($medicine_data);
                    \Log::info("品库添加商品-美团添加药品", [$medicine_data, $res]);
                    if ($res['data'] === 'ok') {
                        Medicine::where('id', $medicine->id)->update(['mt_status' => 1]);
                        $message .= '美团添加商品成功。';
                    } elseif ($res['data'] === 'ng') {
                        $error_msg = $res['error']['msg'] ?? '';
                        if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                            Medicine::where('id', $medicine->id)->update(['mt_status' => 1]);
                            $message .= '美团添加商品成功。';
                        } else {
                            Medicine::where('id', $medicine->id)->update([
                                'mt_error' => $res['error']['msg'] ?? '',
                                'mt_status' => 2
                            ]);
                            $message .= '美团添加商品失败:' . $res['error']['msg'] ?? '' . '。';
                        }
                    }
                }
            }
        }
        if ($request->get('ele')) {
            if (!$shop->waimai_ele) {
                $message .= '饿了么未绑定，无法同步。';
            } else {
                $ele = app('ele');
                $categories = $medicine->categories;
                $cat_arr = [];
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $cat_arr[] = [
                            'category_name' => $category->name
                        ];
                        if ($category->pid != 0) {
                            $f_cat = MedicineCategory::find($category->pid);
                            $cat_params = [
                                'shop_id' => $shop->waimai_ele,
                                'parent_category_id' => 0,
                                'name' => $f_cat->name,
                                'rank' => 100000 - $f_cat->sort > 0 ? 100000 - $f_cat->sort : 1,
                            ];
                            $res = $ele->add_category($cat_params);
                            \Log::info("品库添加商品-饿了么添加一级分类", [$cat_params, $res]);
                            unset($cat_params);
                            $cat_params = [
                                'shop_id' => $shop->waimai_ele,
                                'parent_category_id' => $f_cat->ele_id,
                                'name' => $category->name,
                                'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                            ];
                            $res = $ele->add_category($cat_params);
                            \Log::info("品库添加商品-饿了么添加二级分类", [$cat_params, $res]);
                        } else {
                            $cat_params = [
                                'shop_id' => $shop->waimai_ele,
                                'parent_category_id' => 0,
                                'name' => $category->name,
                                'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                            ];
                            $res = $ele->add_category($cat_params);
                            \Log::info("品库添加商品-饿了么添加一级分类", [$cat_params, $res]);
                        }
                    }
                }
                $medicine_data = [
                    'shop_id' => $shop->waimai_ele,
                    'name' => $medicine->name,
                    'upc' => $medicine->upc,
                    'custom_sku_id' => $medicine->upc,
                    'sale_price' => (int) ($medicine->price * 100),
                    'left_num' => $medicine->stock,
                    'category_list' => $cat_arr,
                    'status' => 1,
                    'base_rec_enable' => true,
                    'photo_rec_enable' => true,
                    'summary_rec_enable' => true,
                    'cat_prop_rec_enable' => true,
                ];
                $res = $ele->add_product($medicine_data);
                \Log::info("品库添加商品-饿了么添加商品", [$medicine_data, $res]);
                if ($res['body']['error'] === 'success') {
                    Medicine::where('id', $medicine->id)->update(['ele_status' => 1]);
                    $message .= '饿了么添加商品成功。';
                } else {
                    $error_msg = $res['body']['error'] ?? '';
                    if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                        Medicine::where('id', $medicine->id)->update(['ele_status' => 1]);
                        $message .= '饿了么添加商品成功。';
                    } else {
                        Medicine::where('id', $medicine->id)->update([
                            'ele_error' => $res['body']['error'] ?? '',
                            'ele_status' => 2
                        ]);
                        $message .= '饿了么添加商品失败:' . $res['body']['error'] ?? '' . '。';
                    }
                }
            }
        }

        return $this->success([], $message);
    }

    public function destroy(Request $request)
    {
        if (!$product_ids = $request->get('product_id')) {
            return $this->error('商品不存在');
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在!');
        }
        $products = Medicine::query()->whereIn('id', $product_ids)->where('shop_id', $shop_id)->get();
        if ($products->isEmpty()) {
            return $this->error('商品不存在!');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('无权限操作此药品');
            }
        }
        $platform = $request->get('platform', []);
        if (!is_array($platform)) {
            $platform = [];
        }
        if (in_array('1', $platform)) {
            Medicine::where('shop_id', $shop_id)->whereIn('id', $product_ids)->delete();
        }
        if (in_array('2', $platform)) {
            if ($shop->waimai_mt) {
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                } else {
                    $meituan = null;
                }
                if ($meituan) {
                    foreach ($products as $product) {
                        $de = [
                            'app_poi_code' => $shop->waimai_mt,
                            'app_medicine_code' => $product->upc,
                        ];
                        $res = $meituan->medicineDelete($de);
                        \Log::info("药品管理删除商品-美团", [$de, $res]);
                    }
                }
            }
        }
        if (in_array('3', $platform)) {
            if ($shop->waimai_ele) {
                $ele = app('ele');
                $product_upcs = [];
                foreach ($products as $product) {
                    $product_upcs[] = $product->upc;
                }
                $de = [
                    'custom_sku_id' => implode(',', $product_upcs),
                    'shop_id' => $shop->waimai_ele,
                ];
                $res = $ele->skuDelete($de);
                \Log::info("药品管理删除商品-饿了么", [$de, $res]);
            }
        }

        return $this->success();
    }

    /**
     * 根据条码获取药品信息
     * @author zhangzhen
     * @data 2023/3/2 2:05 上午
     */
    public function infoByUpc(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if (!$upc = $request->get('upc')) {
            return $this->error('条码不存在');
        }
        // 判断角色
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!$shop = Shop::where('user_id', $request->user()->id)->where('id', $shop_id)->first()) {
                return $this->error('门店不存在!');
            }
        }
        if (!$product = Medicine::query()->where('shop_id', $shop_id)->where('upc', $upc)->first()) {
            return $this->error('药品管理中不存在此药品');
        }
        $data = [
            'id' => $product->id,
            'upc' => $product->upc,
            'name' => $product->name,
            'guidance_price' => $product->guidance_price,
            'price' => $product->price,
            'stock' => $product->stock,
        ];
        return $this->success($data);
    }
}
