<?php

namespace App\Jobs;

use App\Models\Medicine;
use App\Models\MedicineDepot;
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
                // \Log::info('upc3:' . $upc);
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
            }
            Medicine::create($medicine_arr);
        } else {
            $status = false;
        }
        $redis_key = 'medicine_zhongtai_add_job_key_' . $this->log_id;
        $catch = Redis::hget($redis_key, $upc);
        if (!$catch) {
            Redis::hset($redis_key, $upc, 1);
            if ($status) {
                MedicineSyncLog::where('id', $this->log_id)->increment('success');
            } else {
                MedicineSyncLog::where('id', $this->log_id)->increment('fail');
            }
            MedicineSyncLogItem::create([
                'log_id' => $this->log_id,
                'name' => $this->medicine['name'],
                'upc' => $this->medicine['upc'],
                'msg' => $status ? '药品添加成功' : '药品已存在，不能重复添加',
            ]);
        }
        $log = MedicineSyncLog::find($this->log_id);
        if ($log->total <= ($log->success + $log->fail)) {
            Redis::expire($redis_key, 60);
            $log->update([
                'status' => 2,
            ]);
        }
    }
}
