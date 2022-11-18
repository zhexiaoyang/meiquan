<?php

namespace App\Jobs;

use App\Models\Medicine;
use App\Models\MedicineSyncLog;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MedicineSyncMeiTuanItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $key;
    public $params;
    public $api;
    public $shop;
    public $medicine_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $key, array $params, $api, Shop $shop, $medicine_id)
    {
        $this->key = $key;
        $this->params = $params;
        $this->api = $api;
        $this->shop = $shop;
        $this->medicine_id = $medicine_id;
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
            $this->log('创建药品参数', $this->params);
            $res = $meituan->medicineSave($this->params);
            $this->log('创建药品返回', [$res]);
            if ($res['data'] === 'ok') {
                Medicine::where('id', $this->medicine_id)->update(['mt_status' => 1]);
                $status = true;
            } elseif ($res['data'] === 'ng') {
                $error_msg = $res['error']['msg'] ?? '';
                if ((strpos($error_msg, '已存在') !== false) || (strpos($error_msg, '已经存在') !== false)) {
                    Medicine::where('id', $this->medicine_id)->update(['mt_status' => 1]);
                    $status = true;
                } else {
                    $status = false;
                    Medicine::where('id', $this->medicine_id)->update([
                        'mt_error' => $res['error']['msg'] ?? '',
                        'mt_status' => 2
                    ]);
                }
            }
        } catch (\Exception $exception) {
            $this->log('报错啦', [$exception->getMessage()]);
            $status = false;
            Medicine::where('id', $this->medicine_id)
                ->update([
                    'mt_error' => '上传异常',
                    'mt_status' => 2
                ]);
        }

        if ($status !== null) {
            if ($status) {
                MedicineSyncLog::where('id', $this->key)->increment('success');
            } else {
                MedicineSyncLog::where('id', $this->key)->increment('fail');
            }
        }
        $log = MedicineSyncLog::find($this->key);
        if ($log->total <= ($log->success + $log->fail)) {
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
}
