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
use Illuminate\Support\Facades\Redis;

class MedicineImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $medicine;
    public $shop_id;
    public $log_id;
    // public $unique_key;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $shop_id, array $medicine, $log_id)
    {
        $this->shop_id = $shop_id;
        $this->medicine = $medicine;
        $this->log_id = $log_id;
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
        $status = true;

        if (!$medicine = Medicine::where('upc', $upc)->where('shop_id', $this->shop_id)->first()) {
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
            $medicine = Medicine::create($medicine_arr);
            \DB::table('wm_medicine_category')->insert(['medicine_id' => $medicine->id, 'category_id' => $c->id]);
        } else {
            $status = false;
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
                'msg' => $status ? '药品添加成功' : '药品已存在，不能重复添加',
            ]);
        }
        // \Log::info('查询log之前JOB：' . $this->unique_key, [$this->log_id]);
        $log = \DB::table('wm_medicine_sync_logs')->where('id', $this->log_id)->first();
        // \Log::info('查询log之后JOB：' . $this->unique_key, [$log]);
        if (!$log) {
            sleep(3);
            $log = \DB::table('wm_medicine_sync_logs')->where('id', $this->log_id)->first();
            // \Log::info('重新查询log之后JOB：' . $this->unique_key, [$log]);
        }

        if ($log->total <= ($log->success + $log->fail + $redis_number_s + $redis_number_f)) {
            \Log::info('执行一次');
            Redis::expire($redis_key, 60);
            Redis::expire($redis_key_s, 60);
            Redis::expire($redis_key_f, 60);
            $log->update([
                'success' => $log->success + $redis_number_s,
                'fail' => $log->fail + $redis_number_f,
                'status' => 2,
            ]);
        }
    }
}
