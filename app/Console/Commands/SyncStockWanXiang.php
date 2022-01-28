<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStockWanXiang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-wanxiang';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' AND [upc] <> '' AND [upc] IS NOT NULL");
            // ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' and [upc] = '6948060300279'");
        $shop_ids = ['12931358','12931400','13778180','12931402','13505397'];
        \Log::info("aaa", [$data]);

        if (!empty($data)) {
            $data = array_chunk($data, 200);
            $meituan = app("minkang");
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                foreach ($shop_ids as $shop_id) {
                    foreach ($stock_data as $key => $stock_item) {
                        $stock_data[$key]['app_poi_code'] = $shop_id;
                    }
                    $params['app_poi_code'] = $shop_id;
                    $params['medicine_data'] = json_encode($stock_data);

                    $res = $meituan->medicineStock($params);
                    Log::info("万祥门店：{$shop_id}，同步库存返回结果", [$res]);
                }
            }
        }
    }
}
