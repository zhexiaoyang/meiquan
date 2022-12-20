<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TakeoutMedicineStockSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $platform;
    public $platform_id;
    public $upc;
    public $stock;
    public $meituan_bind;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($platform, $platform_id, $upc, $stock, $meituan_bind = 0)
    {
        $this->platform = $platform;
        $this->platform_id = $platform_id;
        $this->upc = $upc;
        $this->stock = $stock;
        $this->meituan_bind = $meituan_bind;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->platform === 1) {
            return $this->meituan();
        } elseif ($this->platform === 2) {
            return $this->ele();
        }
    }

    public function meituan()
    {
        $stock_data[] = [
            'app_medicine_code' => $this->upc,
            'stock' => (int) $this->stock,
        ];

        $params['app_poi_code'] = $this->platform_id;
        $params['medicine_data'] = json_encode($stock_data);

        if ($this->meituan_bind === 4) {
            $meituan = app('minkang');
        } else {
            $meituan = app('meiquan');
            $params['access_token'] = $meituan->getShopToken($this->platform_id);
        }
        $res = $meituan->medicineStock($params);
        \Log::info("药品管理下单同步库存-美团-成功", [$res]);
    }

    public function ele()
    {
        $ele = app('ele');
        $stock_data_ele[] = $this->upc . ':' . (int) $this->stock;
        $ele_params['shop_id'] = $this->platform_id;
        $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
        $res = $ele->skuStockUpdate($ele_params);
        \Log::info("药品管理下单同步库存-饿了么-成功", [$res]);
    }
}
