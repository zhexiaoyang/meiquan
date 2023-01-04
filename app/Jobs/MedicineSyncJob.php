<?php

namespace App\Jobs;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepot;
use App\Models\MedicineSyncLog;
use App\Models\Shop;
use App\Models\WmCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MedicineSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务可以执行的最大秒数 (超时时间)。
     *
     * @var int
     */
    public $timeout = 600;

    protected $shop;
    // 平台 1、美团，2、饿了么
    protected $platform;
    protected $key;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Shop $shop, $platform)
    {
        $this->shop = $shop;
        $this->platform = $platform;
        $this->key = uniqid();
    }

    public function log(string $name, array $data = [])
    {
        $platform = $this->platform === 1 ? '美团' : '饿了么';
        $name = "同步药品JOB|{$this->key}|{$platform}|{$this->shop->id}|{$this->shop->shop_name}|{$name}";
        \Log::info($name, $data);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->platform === 1) {
            $this->meituan();
        } elseif ($this->platform === 2) {
            $this->ele();
        }
    }

    public function meituan()
    {

        if (MedicineSyncLog::where('shop_id', $this->shop->id)->where('status', 1)->where('created_at', '>', date("Y-m-d H:i:s", time() - 610))->first()) {
            $this->log('已存在进行中任务停止任务');
            return;
        }
        $this->log('开始时间点开始');

        // 添加日志
        $log = MedicineSyncLog::create([
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'log_id' => $this->key,
            'total' => 0,
            'success' => 0,
            'fail' => 0,
            'error' => 0,
        ]);

        if ($this->shop->meituan_bind_platform === 4) {
            $meituan = app('minkang');
        } elseif ($this->shop->meituan_bind_platform === 31) {
            $meituan = app('meiquan');
        } else {
            return;
        }
        $total = 0;
        $success = 0;
        $fail = 0;
        // 创建药品分类
        $categories = MedicineCategory::where('shop_id', $this->shop->id)->orderBy('pid')->orderBy('sort')->get();
        $category_key = [];
        foreach ($categories as $k => $category) {
            $category_key[$category->id] = $category->name;
            $category_key[$category->id] = $category->name;
            if (!$category->mt_id) {
                if ($category->pid == 0) {
                    $cat_params = [
                        'app_poi_code' => $this->shop->waimai_mt,
                        'category_code' => $category->id,
                        'category_name' => $category->name,
                        'sequence' => $category->sort,
                    ];
                } else {
                    $cat_params = [
                        'app_poi_code' => $this->shop->waimai_mt,
                        'category_name' => $category_key[$category->pid],
                        'second_category_code' => $category->id,
                        'second_category_name' => $category->name,
                        'second_sequence' => $category->sort,
                    ];
                }
                if ($this->shop->meituan_bind_platform == 31) {
                    $cat_params['access_token'] = $meituan->getShopToken($this->shop->waimai_mt);
                }
                $this->log('分类参数', $cat_params);
                // \Log::info("药品管理任务|门店ID:{$this->shop->id}-分类参数：{$k}", $cat_params);
                $res = $meituan->medicineCatSave($cat_params);
                $this->log('创建分类返回', [$res]);
                $res_data = $res['data'] ?? '';
                $error = $res['error']['msg'] ?? '';
                if (($res_data === 'ok') || (strpos($error, '已经存在') !== false) || (strpos($error, '已存在') !== false)) {
                    $category->mt_id = $category->id;
                    $category->save();
                }
                // \Log::info("药品管理任务|门店ID:{$this->shop->id}-创建分类返回：{$k}", [$res]);
            }
        }
        $medicine_list = Medicine::with('categories')->where('shop_id', $this->shop->id)
            ->whereIn('mt_status', [0, 2])->limit(2000)->get();
        // if ($medicine_list->count() > 200) {
        if (false) {
            // $this->log('走的批量上传');
            // // 批量上传
            // $medicine_list = $medicine_list->chunk(10);
            // $this->log("走的批量上传，组数：" . count($medicine_list));
            // // \Log::info("走的批量上传，组数：" . count($medicine_list));
            // if (!empty($medicine_list)) {
            //     foreach ($medicine_list as $k => $medicines) {
            //         if (!empty($medicines)) {
            //             $total += $medicines->count();
            //             // $success += $medicines->count();
            //             $medicine_data = [];
            //             $medicine_ids = [];
            //             foreach ($medicines as $medicine) {
            //                 $medicine_ids[] = $medicine->id;
            //                 $medicine_category = [];
            //                 if (!empty($medicine->categories)) {
            //                     foreach ($medicine->categories as $item) {
            //                         $medicine_category[] = $item->name;
            //                     }
            //                 }
            //                 if (!empty($medicine_category)) {
            //                     $medicine_data[] = [
            //                         'app_medicine_code' => $medicine->upc,
            //                         'upc' => $medicine->upc,
            //                         'price' => (float) $medicine->price,
            //                         'stock' => $medicine->stock,
            //                         'category_name' => implode(',', $medicine_category),
            //                         'sequence' => $medicine->sequence,
            //                     ];
            //                 }
            //             }
            //             $medicine_params = [
            //                 'app_poi_code' => $this->shop->waimai_mt,
            //                 'medicine_data' => json_encode($medicine_data, JSON_UNESCAPED_UNICODE),
            //             ];
            //             if ($this->shop->meituan_bind_platform == 31) {
            //                 $medicine_params['access_token'] = $meituan->getShopToken($this->shop->waimai_mt);
            //             }
            //             $this->log('药品参数', $medicine_params);
            //             $res = $meituan->medicineBatchSave($medicine_params);
            //             $this->log('创建药品返回', [$res]);
            //             $res_list = [];
            //             $_fail = 0;
            //             if ($res['data'] === 'ok') {
            //                 if (is_array($res['msg'])) {
            //                     $res_list = $res['msg'];
            //                 } elseif (is_string($res['msg'])) {
            //                     $res_string = $res['msg'];
            //                     $res_string = str_replace('批量添加药品结果：', '', $res_string);
            //                     $res_list = json_decode($res_string, true);
            //                 }
            //             } else {
            //                 if ($res['error']['code'] === 705) {
            //                     $_fail += $medicines->count();
            //                 } else {
            //                     $error_string = $res['error']['msg'];
            //                     $error_string = str_replace('批量添加药品结果：', '', $error_string);
            //                     $res_list = json_decode($error_string, true);
            //                 }
            //             }
            //             if (!empty($res_list)) {
            //                 Medicine::whereIn('id', $medicine_ids)->update(['mt_status' => 1]);
            //                 foreach ($res_list as $v) {
            //                     if ((strpos($v['error_msg'], '已存在') !== false) || (strpos($v['error_msg'], '已经存在') !== false)) {
            //                         $upc = $v['app_medicine_code'];
            //                         Medicine::where('shop_id', $this->shop->id)->where('upc', $upc)->update(['mt_status' => 1]);
            //                     } else {
            //                         $_fail++;
            //                         $upc = $v['app_medicine_code'];
            //                         Medicine::where('shop_id', $this->shop->id)->where('upc', $upc)
            //                             ->update([
            //                                 'mt_error' => $v['error_msg'],
            //                                 'mt_status' => 2
            //                             ]);
            //                     }
            //                 }
            //             }
            //             // if (isset($res['data'])) {
            //             //     $_fail = 0;
            //             //     $res_list = [];
            //             //     if ($res['data'] === 'ok') {
            //             //         if (is_array($res['msg'])) {
            //             //             $res_list = $res['msg'];
            //             //         } elseif (is_string($res['msg'])) {
            //             //             $res_string = $res['msg'];
            //             //             $res_string = str_replace('批量添加药品结果：', '', $res_string);
            //             //             $res_list = json_decode($res_string, true);
            //             //         }
            //             //     } else if ($res['data'] === 'ng') {
            //             //         if ($code === 705) {
            //             //             $_fail += $medicines->count();
            //             //         }
            //             //         $error_string = $res['error']['msg'] ?? '';
            //             //         $error_string = str_replace('批量添加药品结果：', '', $error_string);
            //             //         $res_list = json_decode($error_string, true);
            //             //     }
            //             //     if (!empty($res_list)) {
            //             //         Medicine::whereIn('id', $medicine_ids)->update(['mt_status' => 1]);
            //             //         foreach ($res_list as $v) {
            //             //             if ((strpos($v['error_msg'], '已存在') !== false) || (strpos($v['error_msg'], '已经存在') !== false)) {
            //             //                 $upc = $v['app_medicine_code'];
            //             //                 Medicine::where('shop_id', $this->shop->id)->where('upc', $upc)->update(['mt_status' => 1]);
            //             //             } else {
            //             //                 $_fail++;
            //             //                 $upc = $v['app_medicine_code'];
            //             //                 Medicine::where('shop_id', $this->shop->id)->where('upc', $upc)
            //             //                     ->update([
            //             //                         'mt_error' => $v['error_msg'],
            //             //                         'mt_status' => 2
            //             //                     ]);
            //             //             }
            //             //         }
            //             //     }
            //             // }
            //             $fail += $_fail;
            //             $success += $medicines->count() - $_fail;
            //         }
            //     }
            // }
        } else {
            $this->log('走的单个上传');
            if (!empty($medicine_list)) {
                foreach ($medicine_list as $medicine) {
                    $total++;
                    $medicine_category = [];
                    if (!empty($medicine->categories)) {
                        foreach ($medicine->categories as $item) {
                            $medicine_category[] = $item->name;
                        }
                    }
                    $medicine_data = [
                        'app_poi_code' => $this->shop->waimai_mt,
                        'app_medicine_code' => $medicine->upc,
                        'upc' => $medicine->upc,
                        'price' => (float) $medicine->price,
                        'stock' => $medicine->stock,
                        'category_name' => implode(',', $medicine_category),
                        'sequence' => $medicine->sequence,
                        'is_sold_out' => 0,
                    ];
                    if ($this->shop->meituan_bind_platform == 31) {
                        $medicine_data['access_token'] = $meituan->getShopToken($this->shop->waimai_mt);
                    }
                    try {
                        $res = $meituan->medicineSave($medicine_data);
                        $this->log('创建药品返回', [$res]);
                        if ($res['data'] === 'ok') {
                            Medicine::where('id', $medicine->id)->update(['mt_status' => 1]);
                            if ($medicine->depot_id === 0) {
                                $this->add_depot($medicine);
                            }
                            $success++;
                        } elseif ($res['data'] === 'ng') {
                            $error_msg = $res['error']['msg'] ?? '';
                            if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                                Medicine::where('id', $medicine->id)->update(['mt_status' => 1]);
                                if ($medicine->depot_id === 0) {
                                    $this->add_depot($medicine);
                                }
                                $success++;
                            } else {
                                $fail++;
                                Medicine::where('id', $medicine->id)->update([
                                    'mt_error' => $res['error']['msg'] ?? '',
                                    'mt_status' => 2
                                ]);
                            }
                        }
                    } catch (\Exception $exception) {
                        \Log::info('$exception', [$exception->getMessage()]);
                        $fail++;
                        Medicine::where('id', $medicine->id)
                            ->update([
                                'mt_error' => '上传失败',
                                'mt_status' => 2
                            ]);
                    }
                }
            }
        }
        $log->update([
            'total' => $total,
            'success' => $success,
            'fail' => $fail,
            'status' => 2,
        ]);
    }

    public function ele()
    {
        $ele = app('ele');
        // 添加日志
        $log = MedicineSyncLog::create([
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'total' => 0,
            'success' => 0,
            'fail' => 0,
            'error' => 0,
        ]);
        $total = 0;
        $success = 0;
        $fail = 0;
        // 创建药品分类
        $categories = MedicineCategory::where('shop_id', $this->shop->id)->orderBy('pid')->orderBy('sort')->get();
        $category_key = [];
        foreach ($categories as $k => $category) {
            $category_key[$category->id] = $category->name;
            $category_key[$category->id] = $category->name;
            if ($category->pid == 0) {
                $cat_params = [
                    'shop_id' => $this->shop->waimai_ele,
                    'parent_category_id' => 0,
                    'name' => $category->name,
                    'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                ];
            } else {
                $parent = MedicineCategory::find($category->pid);
                $cat_params = [
                    'shop_id' => $this->shop->waimai_ele,
                    'parent_category_id' => $parent->ele_id,
                    'name' => $category->name,
                    'rank' => 100000 - $category->sort > 0 ? 100000 - $category->sort : 1,
                ];
            }
            \Log::info("药品管理任务饿了么|门店ID:{$this->shop->id}-分类参数：{$k}", $cat_params);
            $res = $ele->add_category($cat_params);
            if (isset($res['body']['data']['category_id'])) {
                $category->ele_id = $res['body']['data']['category_id'];
                $category->save();
            }
            \Log::info("药品管理任务饿了么|门店ID:{$this->shop->id}-创建分类返回：{$k}", [$res]);
        }

        // 单个上传
        $medicine_list = Medicine::with('categories')->where('shop_id', $this->shop->id)
            ->whereIn('ele_status', [0, 2])->limit(8000)->get();
        if (!empty($medicine_list)) {
            foreach ($medicine_list as $medicine) {
                $total++;
                $medicine_category = [];
                if (!empty($medicine->categories)) {
                    foreach ($medicine->categories as $item) {
                        $medicine_category[] = [
                            'category_name' => $item->name
                        ];
                    }
                }
                $medicine_data = [
                    'shop_id' => $this->shop->waimai_ele,
                    // 'app_medicine_code' => $medicine->upc,
                    'name' => $medicine->name,
                    'upc' => $medicine->upc,
                    'custom_sku_id' => $medicine->upc,
                    'sale_price' => (int) ($medicine->price * 100),
                    'left_num' => $medicine->stock,
                    'category_list' => $medicine_category,
                    // 'sequence' => $medicine->sequence,
                    'status' => 1,
                    'base_rec_enable' => true,
                    'photo_rec_enable' => true,
                    'summary_rec_enable' => true,
                    'cat_prop_rec_enable' => true,
                ];
                try {
                    $res = $ele->add_product($medicine_data);
                    \Log::info("药品管理任务饿了么|门店ID:{$this->shop->id}-创建药品返回：{$k}", [$res]);
                    if ($res['body']['error'] === 'success') {
                        $success++;
                        Medicine::where('id', $medicine->id)->update(['ele_status' => 1]);
                        if ($medicine->depot_id === 0) {
                            $this->add_depot($medicine);
                        }
                    } else {
                        $error_msg = $res['body']['error'] ?? '';
                        if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                            $success++;
                            Medicine::where('id', $medicine->id)->update(['ele_status' => 1]);
                            if ($medicine->depot_id === 0) {
                                $this->add_depot($medicine);
                            }
                        } else {
                            $fail++;
                            Medicine::where('id', $medicine->id)->update([
                                'ele_error' => $res['body']['error'] ?? '',
                                'ele_status' => 2
                            ]);
                        }
                    }
                } catch (\Exception $exception) {
                    $fail++;
                    Medicine::where('id', $medicine->id)
                        ->update([
                            'ele_error' => '上传失败',
                            'ele_status' => 2
                        ]);
                }
            }
        }

        $log->update([
            'total' => $total,
            'success' => $success,
            'fail' => $fail,
            'status' => 2,
        ]);
        $this->log('开始时间点结束');
    }

    public function add_depot(Medicine $medicine)
    {
        $depot = MedicineDepot::create([
            'name' => $medicine->name,
            'upc' => $medicine->upc,
            'spec' => $medicine->spec ?? '',
            'price' => $medicine->price,
            'sequence' => $medicine->sequence,
        ]);
        \DB::table('wm_depot_medicine_category')->insert([
            'medicine_id' => $depot->id,
            'category_id' => 215,
        ]);
    }
}
