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
    public $log;
    public $online_status;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $shop_id, int $platform, MedicineSyncLog $log, $online_status, array $medicine)
    {
        $this->log = $log;
        $this->shop_id = $shop_id;
        $this->platform = $platform;
        $this->medicine = $medicine;
        $this->online_status = $online_status;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info('aaa', [
            $this->log,
            $this->shop_id,
            $this->platform,
            $this->medicine,
            $this->online_status,
        ]);
        $shop_id = $this->shop_id;
        // $log = $this->log;
        $platform = $this->platform;
        $medicine_data = $this->medicine;
        $msg = '';
        if (!$medicine = Medicine::where('shop_id', $shop_id)->where('upc', $medicine_data['upc'])->first()) {
            return $this->checkEnd2($medicine_data, MedicineSyncLogItem::MEDICINE_NO_FOND);
        }
        Medicine::where('id', $medicine->id)->update($medicine_data);
        if (!$shop = Shop::find($shop_id)) {
            return $this->checkEnd($medicine, '门店不存在');
        }
        $mt = false;
        $ele = false;
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
                            $params['is_sold_out'] = $medicine_data['online_mt'];
                        }
                        if ($shop->meituan_bind_platform == 31) {
                            $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                        }
                        $res = $meituan->medicineUpdate($params);
                        \Log::info('meituan-params', $params);
                        \Log::info('meituan-res', [$res]);
                        if ($res['data'] === 'ok') {
                            $mt = true;
                        } elseif ($res['data'] === 'ng') {
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
                        $params['status'] = $medicine_data['online_ele'] === 1 ? 0 : 1;
                    }
                    // if (isset($medicine_data['sequence'])) {
                    //     $params['sequence'] = $medicine_data['sequence'];
                    // }
                    $res = $eleme->skuUpdate($params);
                    \Log::info('ele-res', [$res]);
                    if ($res['body']['errno'] === 0) {
                        $ele = true;
                    } else {
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
        // \Log::info('bbb', [
        //     $this->log,
        //     $msg,
        //     $status,
        //     $mt,
        //     $ele,
        // ]);
        $log = $this->log;
        $redis_key = 'medicine_job_key_' . $log->log_id;
        $catch = Redis::hget($redis_key, $medicine->id);
        if (!$catch) {
            Redis::hset($redis_key, $medicine->id, 1);
            if ($status) {
                // $log->increment('success');
                MedicineSyncLog::where('id', $log->id)->increment('success');
            } else {
                // $log->increment('fail');
                MedicineSyncLog::where('id', $log->id)->increment('fail');
            }
            if ($mt) {
                // $log->increment('mt_success');
                MedicineSyncLog::where('id', $log->id)->increment('mt_success');
            } else {
                // $log->increment('mt_fail');
                MedicineSyncLog::where('id', $log->id)->increment('mt_fail');
            }
            if ($ele) {
                // $log->increment('ele_success');
                MedicineSyncLog::where('id', $log->id)->increment('ele_success');
            } else {
                // $log->increment('ele_fail');
                MedicineSyncLog::where('id', $log->id)->increment('ele_fail');
            }
        }

        if ($status) {
            $msg = '成功 ' . $msg;
        }

        MedicineSyncLogItem::create([
            'log_id' => $log->id,
            'name' => $medicine->name,
            'upc' => $medicine->upc,
            'msg' => $msg
        ]);

        $log = MedicineSyncLog::find($log->id);
        if ($log->total <= ($log->success + $log->fail)) {
            Redis::expire($redis_key, 60);
            $log->update([
                'status' => 2,
            ]);
        }
    }

    public function checkEnd2($medicine, $msg, $status = false, $mt = false, $ele = false)
    {
        $log = $this->log;
        $redis_key = 'medicine_job_key_' . $log->log_id;
        $catch = Redis::hget($redis_key, $medicine['upc']);
        if (!$catch) {
            Redis::hset($redis_key, $medicine['upc'], 1);
            if ($status) {
                MedicineSyncLog::where('id', $log->id)->increment('success');
            } else {
                MedicineSyncLog::where('id', $log->id)->increment('fail');
            }
            if ($mt) {
                MedicineSyncLog::where('id', $log->id)->increment('mt_success');
            } else {
                MedicineSyncLog::where('id', $log->id)->increment('mt_fail');
            }
            if ($ele) {
                MedicineSyncLog::where('id', $log->id)->increment('ele_success');
            } else {
                MedicineSyncLog::where('id', $log->id)->increment('ele_fail');
            }
        }

        if ($status) {
            $msg = '成功 ' . $msg;
        }

        MedicineSyncLogItem::query()->create([
            'log_id' => $log->id,
            'upc' => $medicine['upc'],
            'msg' => $msg
        ]);

        $log = MedicineSyncLog::find($log->id);
        if ($log->total <= ($log->success + $log->fail)) {
            Redis::expire($redis_key, 60);
            $log->update([
                'status' => 2,
            ]);
        }
    }
}
