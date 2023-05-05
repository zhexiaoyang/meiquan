<?php

namespace App\Jobs;

use App\Models\MedicineSyncLog;
use App\Models\MedicineSyncLogItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class MedicineBatchUpdateGpmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $log_id;
    public $medicine;
    public $meituan_id;
    public $meituan_bind_platform;
    public $ele_id;
    public $gpm;
    public $total;
    public $fail;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($log_id, $medicine, $gpm, $meituan_id, $meituan_bind_platform, $ele_id, $total = 0)
    {
        $this->log_id = $log_id;
        $this->medicine = $medicine;
        $this->gpm = $gpm;
        $this->meituan_id = $meituan_id;
        $this->meituan_bind_platform = $meituan_bind_platform;
        $this->ele_id = $ele_id;
        $this->total = $total;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 毛利率 = （1 - 成本价/销售价）* 100
        // （1 - 成本价/销售价） = 毛利率/100
        // 成本价/销售价 = 1 - 毛利率/100
        // 销售价 = 成本价/(1 - 毛利率/100)
        $meituan_bind_platform = $this->meituan_bind_platform;
        $gpm = $this->gpm;
        $guidance_price = $this->medicine['guidance_price'];
        $new_price = $guidance_price / ( 1 - ($gpm / 100));
        \DB::table('wm_medicines')->where('id', $this->medicine['id'])->update(['price' => $new_price, 'gpm' => $gpm]);
        $mt = true;
        $ele = true;
        $msg = '';
        if ($this->medicine['mt_status'] == 1) {
            $meituan = null;
            if ($meituan_bind_platform === 4) {
                $meituan = app('minkang');
            } elseif ($meituan_bind_platform === 31) {
                $meituan = app('meiquan');
            }
            if ($meituan) {
                try {
                    $params = [
                        'app_poi_code' => $this->meituan_id,
                        'app_medicine_code' => $this->medicine['upc'],
                        'price' => $new_price,
                    ];
                    if ($this->meituan_bind_platform === 31) {
                        $params['access_token'] = $meituan->getShopToken($this->meituan_id);
                    }
                    $res = $meituan->medicineUpdate($params);
                    if ($res['data'] === 'ng') {
                        $mt = false;
                        if (!empty($res['error']['msg'])) {
                            $msg .= '美团错误：' . $res['error']['msg'] . '。';
                        }
                    }
                } catch (\Exception $exception) {
                    \Log::info('毛利率同步美团价格失败', [$exception->getMessage(), $exception->getFile(), $exception->getLine()]);
                }
            // } else {
            //     $msg .= '门店未绑定美团。';
            }
        // } else {
        //     $msg .= '药品未同步美团。';
        }
        if ($this->medicine['ele_status'] == 1) {
            if ($this->ele_id) {
                // $msg .= '门店未绑定饿了么。';
            // } else {
                $eleme = app('ele');
                $params = [
                    'shop_id' => $this->ele_id,
                    'custom_sku_id' => $this->medicine['upc'],
                    'sale_price' => (int) ($new_price * 100)
                ];
                $res = $eleme->skuUpdate($params);
                if ($res['body']['errno'] !== 0) {
                    $mt = false;
                    if (!empty($res['body']['error'])) {
                        $msg .= '饿了么错误：' . $res['body']['error'] . '。';
                    }
                }
            }
        // } else {
        //     $msg .= '药品未同步饿了么。';
        }
        // 修改日志
        $redis_key = 'medicine_job_key_' . $this->log_id;
        $redis_key_success = 'medicine_job_key_success_' . $this->log_id;
        $redis_key_fail = 'medicine_job_key_fail_' . $this->log_id;
        $redis_number_success =  Redis::get($redis_key_success);
        $redis_number_fail = Redis::get($redis_key_fail);
        $catch = Redis::hget($redis_key, $this->medicine['id']);
        if (!$catch) {
            Redis::hset($redis_key, $this->medicine['id'], 1);
            if (!$mt || !$ele) {
                $msg = '失败 ' . $msg;
                // \Log::info("毛利率:{$gpm},原线上价格:{$price},成本价:{$guidance_price},新价格:{$new_price}");
                // $res_num = MedicineSyncLog::where('id', $this->log_id)->increment('fail');
                $redis_number_fail = Redis::incr($redis_key_fail);
            } else {
                $redis_number_success = Redis::incr($redis_key_success);
                // $res_num = MedicineSyncLog::where('id', $this->log_id)->increment('success');
            }
            // if ($mt) {
            //     MedicineSyncLog::where('id', $this->log_id)->increment('mt_success');
            // } else {
            //     MedicineSyncLog::where('id', $this->log_id)->increment('mt_fail');
            // }
            // if ($ele) {
            //     MedicineSyncLog::where('id', $this->log_id)->increment('ele_success');
            // } else {
            //     MedicineSyncLog::where('id', $this->log_id)->increment('ele_fail');
            // }
            MedicineSyncLogItem::create([
                'log_id' => $this->log_id,
                'name' => $this->medicine['name'],
                'upc' => $this->medicine['upc'],
                'msg' => $msg
            ]);
            \Log::info($this->total . '|' . $redis_number_success . '|' . $redis_number_fail);
            if ($this->total <= ($redis_number_success + $redis_number_fail)) {
                Redis::expire($redis_key, 60);
                Redis::expire($redis_key_success, 60);
                Redis::expire($redis_key_fail, 60);
                MedicineSyncLog::where('id', $this->log_id)->update([
                    'success' => $redis_number_success,
                    'fail' => $redis_number_fail,
                    'status' => 2,
                ]);
            }
        }
    }
}
