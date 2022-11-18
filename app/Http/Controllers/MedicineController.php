<?php

namespace App\Http\Controllers;

use App\Imports\MedicineImport;
use App\Jobs\MedicineSyncJob;
use App\Jobs\MedicineSyncMeiTuanItemJob;
use App\Models\Medicine;
use App\Models\MedicineCategory;
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
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
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
        $logs = MedicineSyncLog::where('shop_id', $shop_id)->orderByDesc('id')->limit(2)->get();

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
            if (!in_array($shop_id, $request->user()->shops()->pluck('id'))) {
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
        if ($mt = $request->get('mt')) {
            $query->where('mt_status', $mt);
        }
        if ($ele = $request->get('ele')) {
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

    public function sync(Request $request)
    {
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
            if (!in_array($shop_id, $request->user()->shops()->pluck('id'))) {
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
            MedicineSyncJob::dispatch($shop, $platform);
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
        $medicine_list = Medicine::with('categories')->where('shop_id', $shop->id)
            ->whereIn('mt_status', [0, 2])->limit(5000)->get();
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
                MedicineSyncMeiTuanItemJob::dispatch($log->id, $medicine_data, $shop->meituan_bind_platform, $shop, $medicine->id);

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
    //         if (!in_array($shop_id, $request->user()->shops()->pluck('id'))) {
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
}
