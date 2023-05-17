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

class MedicineSyncEleItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $key;
    public $params;
    public $shop_id;
    public $shop_name;
    public $medicine_id;
    public $depot_id;
    public $name;
    public $upc;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $key, array $params, $shop_id, $shop_name, $medicine_id, $depot_id = 0, $name = '', $upc = '')
    {
        // 日志ID
        $this->key = $key;
        // 药品数据
        $this->params = $params;
        // 门店信息
        $this->shop_id = $shop_id;
        $this->shop_name = $shop_name;
        // 药品ID
        $this->medicine_id = $medicine_id;
        // 品库ID
        $this->depot_id = $depot_id;
        // 药品名称
        $this->name = $name;
        // 药品条码
        $this->upc = $upc;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ele = app('ele');
        try {
            // $this->log('创建药品参数', $this->params);
            $res = $ele->add_product($this->params);
            // $this->log('创建药品返回', [$res]);
            $res = $ele->add_product($this->params);
            if ($res['body']['error'] === 'success') {
                if (Medicine::where('id', $this->medicine_id)->update(['ele_status' => 1, 'online_ele' => 1])) {
                    $status = true;
                }
            } else {
                $error_msg = $res['body']['error'] ?? '';
                if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                    $update_data = ['ele_status' => 1, 'online_ele' => 1];
                    // 库存大于0 为上架状态
                    // if ($this->params['left_num'] > 0) {
                    //     $update_data['online_ele'] = 1;
                    // }
                    if (Medicine::where('id', $this->medicine_id)->update($update_data)) {
                        $status = true;
                    }
                    // if ($medicine->depot_id === 0) {
                    //     $this->add_depot($medicine);
                    // }
                } else {
                    if (Medicine::where('id', $this->medicine_id)->update([
                        'ele_error' => $res['body']['error'] ?? '饿了么失败',
                        'ele_status' => 2
                    ])) {
                        // MedicineSyncLog::where('id', $this->key)->increment('fail');$res['body']['error']
                        $status = false;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->log('报错啦', [$exception->getMessage()]);
            $status = false;
            // MedicineSyncLog::where('id', $this->key)->increment('fail');
            Medicine::where('id', $this->medicine_id)
                ->update([
                    'ele_error' => '上传异常',
                    'ele_status' => 2
                ]);
        }

        $redis_key = 'medicine_ele_job_key_' . $this->key;
        $catch = Redis::hget($redis_key, $this->medicine_id);
        if ($status !== null && !$catch) {
            Redis::hset($redis_key, $this->medicine_id, 1);
            if ($status) {
                // if (intval($this->depot_id) === 0) {
                //     $this->add_depot();
                // }
                MedicineSyncLog::where('id', $this->key)->increment('success');
            } else {
                MedicineSyncLog::where('id', $this->key)->increment('fail');
            }
            MedicineSyncLogItem::create([
                'log_id' => $this->key,
                'name' => $this->params['name'],
                'upc' => $this->params['upc'],
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
        $name = "同步药品JOB|日志ID{$this->key}|饿了么|{$this->shop_id}|{$this->shop_name}|{$name}";
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
