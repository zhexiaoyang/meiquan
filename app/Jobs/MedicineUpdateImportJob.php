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

class MedicineUpdateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $medicine;
    public $platform;
    public $shop_id;
    public $log_id;
    public $online_status;
    public $log_total;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $shop_id, int $platform, $log_id, $online_status, array $medicine, $log_total = 0)
    {
        $this->log_id = $log_id;
        $this->shop_id = $shop_id;
        $this->platform = $platform;
        $this->medicine = $medicine;
        // 0 上架，1 下架
        $this->online_status = $online_status;
        $this->log_total = $log_total;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // \Log::info('aaa', [
        //     $this->log_id,
        //     $this->shop_id,
        //     $this->platform,
        //     $this->medicine,
        //     $this->online_status,
        // ]);
        $shop_id = $this->shop_id;
        // $log = $this->log;
        $platform = $this->platform;
        $medicine_data = $this->medicine;
        $update_store_id_status = false;
        $msg = '';
        if (!$medicine = Medicine::where('shop_id', $shop_id)->where('upc', $medicine_data['upc'])->first()) {
            return $this->checkEnd2($medicine_data, MedicineSyncLogItem::MEDICINE_NO_FOND);
        }
        if (!empty($medicine_data['store_id']) && $medicine->store_id != $medicine_data['store_id']) {
            $update_store_id_status = true;
        }
        // \DB::table('wm_medicines')->where('id', $medicine->id)->update($medicine_data);
        if (isset($medicine_data['price']) && $medicine_data['price'] > 0 && $medicine->guidance_price > 0) {
            $medicine_data['gpm'] = ($medicine_data['price'] - $medicine->guidance_price) / $medicine_data['price'] * 100;
        }
        if (isset($medicine_data['down_price']) && $medicine_data['down_price'] > 0 && $medicine->guidance_price > 0) {
            $medicine_data['down_gpm'] = ($medicine_data['down_price'] - $medicine->guidance_price) / $medicine_data['down_price'] * 100;
        }
        Medicine::where('id', $medicine->id)->update($medicine_data);
        if (!$shop = Shop::find($shop_id)) {
            return $this->checkEnd($medicine, '门店不存在');
        }
        $mt = true;
        $ele = true;
        if ($platform === 0 || $platform === 1) {
            // 同步美团
            if ($medicine->mt_status === 2) {
                $msg .= MedicineSyncLogItem::MEDICINE_NO_SYNC_FAIL_MEITUAN;
            } elseif ($medicine->mt_status === 0) {
                $msg .= MedicineSyncLogItem::MEDICINE_NO_SYNC_MEITUAN;
            } else {
                if (!$shop->waimai_mt) {
                    $msg .= MedicineSyncLogItem::NOT_BIND_MEITUAN;
                } else {
                    if (!in_array($shop->meituan_bind_platform, [4, 31])) {
                        $msg .= MedicineSyncLogItem::NOT_BIND_MEITUAN;
                    } else {
                        if ($shop->meituan_bind_platform === 4) {
                            $meituan = app('minkang');
                        // } elseif ($shop->meituan_bind_platform === 31) {
                        //     $meituan = app('meiquan');
                        } else {
                            $meituan = app('meiquan');
                        }
                        if ($update_store_id_status) {
                            // 更新商家商品编码
                        }
                        $params = [
                            'app_poi_code' => $shop->waimai_mt,
                            'app_medicine_code' => $medicine->upc,
                        ];
                        if (isset($medicine_data['price'])) {
                            $params['price'] = $medicine_data['price'];
                        }
                        if (isset($medicine_data['stock'])) {
                            $params['stock'] = $medicine_data['stock'];
                        }
                        if (isset($medicine_data['sequence'])) {
                            $params['sequence'] = $medicine_data['sequence'];
                        }
                        if (isset($medicine_data['online_mt'])) {
                            $params['is_sold_out'] = $medicine_data['online_mt'] === 1 ? 0 : 1;
                        }
                        if ($shop->meituan_bind_platform == 31) {
                            $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                        }
                        $res = $meituan->medicineUpdate($params);
                        // \Log::info('meituan-params', $params);
                        // \Log::info('meituan-res', [$res]);
                        // if ($res['data'] === 'ok') {
                        //     $mt = true;
                        // } else
                        if ($res['data'] === 'ng') {
                            $mt = false;
                            if (!empty($res['error']['msg'])) {
                                $msg .= '美团错误：' . $res['error']['msg'] . '。';
                            }
                        }
                    }
                }
            }
        }
        if ($platform === 0 || $platform === 2) {
            // 同步饿了么
            if ($medicine->ele_status === 2) {
                $msg .= MedicineSyncLogItem::MEDICINE_NO_SYNC_FAIL_ELE;
            } elseif ($medicine->ele_status === 0) {
                $msg .= MedicineSyncLogItem::MEDICINE_NO_SYNC_ELE;
            } else {
                if (!$shop->waimai_ele) {
                    $msg .= MedicineSyncLogItem::NOT_BIND_ELE;
                } else {
                    $eleme = app('ele');
                    if ($update_store_id_status) {
                        // 更新商家商品编码
                    }
                    $params = [
                        'shop_id' => $shop->waimai_ele,
                        'custom_sku_id' => $medicine->upc,
                    ];
                    if (isset($medicine_data['price'])) {
                        $params['sale_price'] = (int) ($medicine_data['price']* 100);
                    }
                    if (isset($medicine_data['stock'])) {
                        $params['left_num'] = $medicine_data['stock'];
                    }
                    if (isset($medicine_data['online_ele'])) {
                        $params['status'] = $medicine_data['online_ele'];
                    }
                    // if (isset($medicine_data['sequence'])) {
                    //     $params['sequence'] = $medicine_data['sequence'];
                    // }
                    $res = $eleme->skuUpdate($params);
                    // \Log::info('ele-res', [$res]);
                    if ($res['body']['errno'] !== 0) {
                        $ele = false;
                        if (!empty($res['body']['error'])) {
                            $msg .= '饿了么错误：' . $res['body']['error'] . '。';
                        }
                        // $msg .= $res['body']['error'] ?? '';
                    }
                }
            }
        }
        return $this->checkEnd($medicine, $msg, $mt && $ele, $mt, $ele);
    }

    public function checkEnd($medicine, $msg, $status = false, $mt = false, $ele = false)
    {
        // \Log::info('aaa', [$medicine, $msg, $status, $mt, $ele]);
        $redis_key = 'medicine_job_key_' . $this->log_id;
        $redis_key_number = 'medicine_job_key_number_' . $this->log_id;
        $redis_success = Redis::hget($redis_key_number, 'success');
        $redis_fail = Redis::hget($redis_key_number, 'fail');
        $redis_success_mt = Redis::hget($redis_key_number, 'mt_success');
        $redis_fail_mt = Redis::hget($redis_key_number, 'mt_fail');
        $redis_success_ele = Redis::hget($redis_key_number, 'ele_success');
        $redis_fail_ele = Redis::hget($redis_key_number, 'ele_fail');
        $catch = Redis::hget($redis_key_number, $medicine->id);
        if (!$catch) {
            Redis::hset($redis_key, $medicine->id, 1);
            if ($status) {
                // $log->increment('success');
                // MedicineSyncLog::where('id', $this->log_id)->increment('success');
                $redis_success = Redis::hincrby($redis_key_number, 'success', 1);
            } else {
                // $log->increment('fail');
                // MedicineSyncLog::where('id', $this->log_id)->increment('fail');
                $redis_fail = Redis::hincrby($redis_key_number, 'fail', 1);
            }
            if ($mt) {
                // $log->increment('mt_success');
                // MedicineSyncLog::where('id', $this->log_id)->increment('mt_success');
                $redis_success_mt = Redis::hincrby($redis_key_number, 'mt_success', 1);
            } else {
                // $log->increment('mt_fail');
                // MedicineSyncLog::where('id', $this->log_id)->increment('mt_fail');
                $redis_fail_mt = Redis::hincrby($redis_key_number, 'mt_fail', 1);
            }
            if ($ele) {
                // $log->increment('ele_success');
                // MedicineSyncLog::where('id', $this->log_id)->increment('ele_success');
                $redis_success_ele = Redis::hincrby($redis_key_number, 'ele_success', 1);
            } else {
                // $log->increment('ele_fail');
                // MedicineSyncLog::where('id', $this->log_id)->increment('ele_fail');
                $redis_fail_ele = Redis::hincrby($redis_key_number, 'ele_fail', 1);
            }
        }

        if ($status) {
            $msg = '成功 ' . $msg;
        }

        MedicineSyncLogItem::create([
            'log_id' => $this->log_id,
            'name' => $medicine->name,
            'upc' => $medicine->upc,
            'msg' => $msg
        ]);
        // \Log::info("success:{$redis_success}, fail:{$redis_fail}");

        // $log = MedicineSyncLog::find($this->log_id);
        if ($this->log_total <= ($redis_success + $redis_fail)) {
            Redis::expire($redis_key, 60);
            Redis::expire($redis_key_number, 60);
            MedicineSyncLog::where('id', $this->log_id)->update([
                'success' => $redis_success,
                'fail' => $redis_fail,
                'mt_success' => $redis_success_mt,
                'mt_fail' => $redis_fail_mt,
                'ele_success' => $redis_success_ele,
                'ele_fail' => $redis_fail_ele,
                'status' => 2,
            ]);
        }
    }

    public function checkEnd2($medicine, $msg, $status = false, $mt = false, $ele = false)
    {
        $redis_key = 'medicine_job_key_' . $this->log_id;
        $redis_key_number = 'medicine_job_key_number_' . $this->log_id;
        $redis_success = Redis::hget($redis_key, 'success');
        $redis_fail = Redis::hget($redis_key, 'fail');
        $redis_success_mt = Redis::hget($redis_key, 'mt_success');
        $redis_fail_mt = Redis::hget($redis_key, 'mt_fail');
        $redis_success_ele = Redis::hget($redis_key, 'ele_success');
        $redis_fail_ele = Redis::hget($redis_key, 'ele_fail');
        $catch = Redis::hget($redis_key, $medicine['upc']);
        if (!$catch) {
            Redis::hset($redis_key, $medicine['upc'], 1);
            if ($status) {
                // MedicineSyncLog::where('id', $this->log_id)->increment('success');
                $redis_success = Redis::hincrby($redis_key_number, 'success', 1);
            } else {
                // MedicineSyncLog::where('id', $this->log_id)->increment('fail');
                $redis_fail = Redis::hincrby($redis_key_number, 'fail', 1);
            }
            if ($mt) {
                // MedicineSyncLog::where('id', $this->log_id)->increment('mt_success');
                $redis_success_mt = Redis::hincrby($redis_key_number, 'mt_success', 1);
            } else {
                // MedicineSyncLog::where('id', $this->log_id)->increment('mt_fail');
                $redis_fail_mt = Redis::hincrby($redis_key_number, 'mt_fail', 1);
            }
            if ($ele) {
                // MedicineSyncLog::where('id', $this->log_id)->increment('ele_success');
                $redis_success_ele = Redis::hincrby($redis_key_number, 'ele_success', 1);
            } else {
                // MedicineSyncLog::where('id', $this->log_id)->increment('ele_fail');
                $redis_fail_ele = Redis::hincrby($redis_key_number, 'ele_fail', 1);
            }
        }

        if ($status) {
            $msg = '成功 ' . $msg;
        }

        MedicineSyncLogItem::create([
            'log_id' => $this->log_id,
            'upc' => $medicine['upc'],
            'msg' => $msg
        ]);

        // $log = MedicineSyncLog::find($this->log_id);
        // if ($log->total <= ($log->success + $log->fail)) {
        //     Redis::expire($redis_key, 60);
        //     $log->update([
        //         'status' => 2,
        //     ]);
        // }
        if ($this->log_total <= ($redis_success + $redis_fail)) {
            Redis::expire($redis_key, 60);
            Redis::expire($redis_key_number, 60);
            MedicineSyncLog::where('id', $this->log_id)->update([
                'success' => $redis_success,
                'fail' => $redis_fail,
                'mt_success' => $redis_success_mt,
                'mt_fail' => $redis_fail_mt,
                'ele_success' => $redis_success_ele,
                'ele_fail' => $redis_fail_ele,
                'status' => 2,
            ]);
        }
    }
}
