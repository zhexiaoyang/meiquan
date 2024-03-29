<?php

namespace App\Jobs;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepot;
use App\Models\MedicineDepotCategory;
use App\Models\MedicineSyncLog;
use App\Models\MedicineSyncLogItem;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MedicineImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $medicine;
    public $shop_id;
    public $log_id;
    public $log_total;
    // public $unique_key;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $shop_id, array $medicine, $log_id, $log_total = 0)
    {
        $this->shop_id = $shop_id;
        $this->medicine = $medicine;
        $this->log_id = $log_id;
        $this->log_total = $log_total;
        // $this->unique_key = uniqid();
        // \Log::info('进入JOB：' . $this->unique_key, [$log_id]);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $upc = $this->medicine['upc'];
        $price = $this->medicine['price'];
        $stock = $this->medicine['stock'];
        $cost = $this->medicine['guidance_price'];
        $down_price = $this->medicine['down_price'] ?? 0;
        $sequence = $this->medicine['sequence'] ?? 1000;
        $store_id = $this->medicine['store_id'] ?? '';
        $status = true;
        $msg = '药品添加成功';
        $error_status = false;

        if ($store_id && $m = Medicine::where('store_id', $store_id)->where('shop_id', $this->shop_id)->first()) {
            if ($upc != $m->upc) {
                $error_status = true;
                $msg = '该商家商品编码已存在，绑定商品条码：' . $m->upc;
            }
        }

        if ($error_status) {
            $status = false;
        } else {
            if (!Medicine::where('upc', $upc)->where('shop_id', $this->shop_id)->first()) {
                if ($depot = MedicineDepot::where('upc', $upc)->first()) {
                    // 品库中有此商品

                    // 创建分类
                    // 查找该商品在品库中的所有分类ID
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
                                                    'shop_id' => $this->shop_id,
                                                    'name' => $depot_category_parent->name,
                                                ],
                                                [
                                                    'shop_id' => $this->shop_id,
                                                    'pid' => 0,
                                                    'name' => $depot_category_parent->name,
                                                    'sort' => $depot_category_parent->sort,
                                                ]
                                            );
                                        } catch (\Exception $exception) {
                                            $category_parent = MedicineCategory::where([
                                                'shop_id' => $this->shop_id,
                                                'name' => $depot_category_parent->name,
                                            ])->first();
                                            \Log::info("创建分类失败(父级)|shop_id:{$this->shop_id}|name:{$depot_category_parent->name}|重新查询结果：", [$category_parent]);
                                        }
                                        $pid = $category_parent->id;
                                    }
                                }
                                try {
                                    $c = MedicineCategory::firstOrCreate(
                                        [
                                            'shop_id' => $this->shop_id,
                                            'name' => $depot_category->name,
                                        ],
                                        [
                                            'shop_id' => $this->shop_id,
                                            'pid' => $pid,
                                            'name' => $depot_category->name,
                                            'sort' => $depot_category->sort,
                                        ]
                                    );
                                } catch (\Exception $exception) {
                                    $c = MedicineCategory::where([
                                        'shop_id' => $this->shop_id,
                                        'name' => $depot_category->name,
                                    ])->first();
                                    \Log::info("创建分类失败|shop_id:{$this->shop_id}|name:{$depot_category->name}|重新查询结果：", [$c]);
                                }
                            }
                        }
                    }
                    // 创建商品数组
                    $medicine_arr = [
                        'shop_id' => $this->shop_id,
                        'name' => $depot->name,
                        'upc' => $depot->upc,
                        'cover' => $depot->cover,
                        'brand' => $depot->brand,
                        'spec' => $depot->spec,
                        'price' => $price,
                        'stock' => $stock,
                        'guidance_price' => $cost,
                        'depot_id' => $depot->id,
                        'down_price' => $down_price,
                        'sequence' => $sequence,
                        'store_id' => $store_id,
                    ];
                } else {
                    // 品库中没有此商品
                    $l = strlen($upc);
                    $name = $this->medicine['name'];
                    if ($l >= 7 && $l <= 19) {
                        $_depot = MedicineDepot::create([
                            'name' => $name,
                            'upc' => $upc
                        ]);
                        \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $_depot->id, 'category_id' => 215]);
                    }
                    $medicine_arr = [
                        'shop_id' => $this->shop_id,
                        'name' => $name,
                        'upc' => $upc,
                        'brand' => '',
                        'spec' => '',
                        'price' => $price,
                        'stock' => $stock,
                        'guidance_price' => $cost,
                        'depot_id' => 0,
                        'down_price' => $down_price,
                        'sequence' => $sequence,
                        'store_id' => $store_id,
                    ];
                    // 创建-暂未分类
                    $c = MedicineCategory::firstOrCreate(
                        [
                            'shop_id' => $this->shop_id,
                            'name' => '暂未分类',
                        ],
                        [
                            'shop_id' => $this->shop_id,
                            'pid' => 0,
                            'name' => '暂未分类',
                            'sort' => 1000,
                        ]
                    );
                }
                try {
                    DB::transaction(function () use ($medicine_arr, $c) {
                        $medicine = Medicine::create($medicine_arr);
                        \DB::table('wm_medicine_category')->insert(['medicine_id' => $medicine->id, 'category_id' => $c->id]);
                    });
                } catch (\Exception $exception) {
                    \Log::info("商品管理批量导入创建药品失败", [$medicine_arr, $exception->getMessage(),$exception->getFile(),$exception->getLine()]);
                    $status = false;
                    $msg = '添加药品失败，请检查数据填写是否正确';
                }
            } else {
                $status = false;
                $msg = '药品已存在，不能重复添加';
            }
        }

        $redis_key = 'medicine_zhongtai_add_job_key_' . $this->log_id;
        $redis_key_s = 'medicine_zhongtai_add_job_key_s' . $this->log_id;
        $redis_key_f = 'medicine_zhongtai_add_job_key_f' . $this->log_id;
        $catch = Redis::hget($redis_key, $upc);
        $redis_number_s = 0;
        $redis_number_f = 0;
        if (!$catch) {
            Redis::hset($redis_key, $upc, 1);
            if ($status) {
                $redis_number_s = Redis::incr($redis_key_s);
                // MedicineSyncLog::where('id', $this->log_id)->increment('success');
            } else {
                $redis_number_f = Redis::incr($redis_key_f);
                // MedicineSyncLog::where('id', $this->log_id)->increment('fail');
            }
            // $redis_number_s = Redis::get($redis_key_s);
            // $redis_number_f = Redis::get($redis_key_f);
            MedicineSyncLogItem::create([
                'log_id' => $this->log_id,
                'name' => $this->medicine['name'],
                'upc' => $this->medicine['upc'],
                'msg' => $msg,
            ]);
        }
        // // \Log::info('查询log之前JOB：' . $this->unique_key, [$this->log_id]);
        // $log = \DB::table('wm_medicine_sync_logs')->where('id', $this->log_id)->first();
        // // \Log::info('查询log之后JOB：' . $this->unique_key, [$log]);
        // if (!$log) {
        //     sleep(3);
        //     $log = \DB::table('wm_medicine_sync_logs')->where('id', $this->log_id)->first();
        //     // \Log::info('重新查询log之后JOB：' . $this->unique_key, [$log]);
        // }

        if ($this->log_total <= ($redis_number_s + $redis_number_f)) {
            Redis::expire($redis_key, 60);
            Redis::expire($redis_key_s, 60);
            Redis::expire($redis_key_f, 60);
            MedicineSyncLog::where('id', $this->log_id)->update([
                'success' => $redis_number_s,
                'fail' => $redis_number_f,
                'status' => 2,
            ]);
        }
    }
}
