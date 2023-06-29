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

class MedicineSyncMeiTuanItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $key;
    public $params;
    public $api;
    public $shop;
    public $medicine_id;
    public $depot_id;
    public $name;
    public $upc;
    public $update;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $key, array $params, $api, Shop $shop, $medicine_id, $depot_id = 0, $name = '', $upc = '', $update = false)
    {
        // 日志ID
        $this->key = $key;
        // 药品数据
        $this->params = $params;
        // 美团绑定应用
        $this->api = $api;
        // 门店信息
        $this->shop = $shop;
        // 药品ID
        $this->medicine_id = $medicine_id;
        // 品库ID
        $this->depot_id = $depot_id;
        // 药品名称
        $this->name = $name;
        // 药品条码
        $this->upc = $upc;
        // 是否更新操作
        $this->update = $update;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $status = null;
        if ($this->api === 4) {
            $meituan = app('minkang');
        } elseif ($this->api === 31) {
            $meituan = app('meiquan');
        } else {
            return;
        }
        try {
            if ($this->update) {
                $this->log('更新药品参数', $this->params);
                // $update_params = [
                //     'app_poi_code' => $this->params['app_poi_code'],
                //     'app_medicine_code' => $this->params['app_medicine_code'],
                //     'price' => $this->params['price'],
                //     'stock' => $this->params['stock'],
                // ];
                $res = $meituan->medicineUpdate($this->params);
                $this->log('更新药品返回', [$res]);
                if ($res['data'] === 'ok') {
                    if (Medicine::where('id', $this->medicine_id)->update(['mt_status' => 1, 'online_mt' => 1])) {
                        // MedicineSyncLog::where('id', $this->key)->increment('success');
                        $status = true;
                    }
                } elseif ($res['data'] === 'ng') {
                    $error_msg = $res['error']['msg'] ?? '';
                    $error_msg = substr($error_msg, 0, 200);
                    // if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                    //     $update_data = ['mt_status' => 1, 'online_mt' => 1];
                    //     // 库存大于0 为上架状态
                    //     // if ($this->params['stock'] > 0) {
                    //     //     $update_data['online_mt'] = 1;
                    //     // }
                    //     if (Medicine::where('id', $this->medicine_id)->update($update_data)) {
                    //         // MedicineSyncLog::where('id', $this->key)->increment('success');
                    //         $status = true;
                    //     }
                    // } else {
                        if (Medicine::where('id', $this->medicine_id)->update(['mt_error' => $res['error']['msg'] ?? '','mt_status' => 2])) {
                            // MedicineSyncLog::where('id', $this->key)->increment('fail');
                            $status = false;
                        }
                    // }
                }
            } else {
                $this->log('创建药品参数', $this->params);
                $res = $meituan->medicineSave($this->params);
                $this->log('创建药品返回', [$res]);
                if ($res['data'] === 'ok') {
                    if (Medicine::where('id', $this->medicine_id)->update(['mt_status' => 1, 'online_mt' => 1])) {
                        // MedicineSyncLog::where('id', $this->key)->increment('success');
                        $status = true;
                    }
                } elseif ($res['data'] === 'ng') {
                    $error_msg = $res['error']['msg'] ?? '';
                    $error_msg = substr($error_msg, 0, 200);
                    if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                        $update_data = ['mt_status' => 1, 'online_mt' => 1];
                        // 库存大于0 为上架状态
                        // if ($this->params['stock'] > 0) {
                        //     $update_data['online_mt'] = 1;
                        // }
                        if (Medicine::where('id', $this->medicine_id)->update($update_data)) {
                            // MedicineSyncLog::where('id', $this->key)->increment('success');
                            $status = true;
                        }
                    } else {
                        if (Medicine::where('id', $this->medicine_id)->update(['mt_error' => $res['error']['msg'] ?? '','mt_status' => 2])) {
                            // MedicineSyncLog::where('id', $this->key)->increment('fail');
                            $status = false;
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->log('报错啦', [$exception->getMessage()]);
            $status = false;
            // MedicineSyncLog::where('id', $this->key)->increment('fail');
            Medicine::where('id', $this->medicine_id)
                ->update([
                    'mt_error' => '上传异常',
                    'mt_status' => 2
                ]);
        }

        $redis_key = 'medicine_job_key_' . $this->key;
        $catch = Redis::hget($redis_key, $this->medicine_id);
        if ($status !== null && !$catch) {
            Redis::hset($redis_key, $this->medicine_id, 1);
            if ($status) {
                if (intval($this->depot_id) === 0) {
                    $this->add_depot();
                }
                MedicineSyncLog::where('id', $this->key)->increment('success');
            } else {
                MedicineSyncLog::where('id', $this->key)->increment('fail');
            }
            MedicineSyncLogItem::create([
                'log_id' => $this->key,
                'name' => $this->params['name'] ?? '',
                'upc' => $this->params['upc'] ?? '',
                'msg' => $status ? '成功' : ($error_msg ?: '失败'),
            ]);
        }
        $log = MedicineSyncLog::find($this->key);
        if ($log->total <= ($log->success + $log->fail)) {
            Redis::expire($redis_key, 60);
            $log->update([
                'status' => 2,
            ]);
        }
    }

    public function log(string $name, array $data = [])
    {
        $name = "同步药品JOB|日志ID{$this->key}|美团|{$this->shop->id}|{$this->shop->shop_name}|{$name}";
        \Log::info($name, $data);
    }

    public function add_depot()
    {
        if (!MedicineDepot::where('upc', $this->upc)->first()) {
            $depot = MedicineDepot::create([
                'name' => $this->name,
                'upc' => $this->upc,
            ]);
            \DB::table('wm_depot_medicine_category')->insert([
                'medicine_id' => $depot->id,
                'category_id' => 215,
            ]);
        }
    }
}
