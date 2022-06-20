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
        $this->info('------------万祥同步库存开始------------');
        $this->info('中心仓库存同步-开始......');
        Log::info('中心仓库存同步-开始......');
        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' AND [upc] <> '' AND [upc] IS NOT NULL");
            // ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' and [upc] = '6948060300279'");
        // $shop_ids = ['12931358','12931400','13778180','12931402','13505397'];
        $shop_ids = ['12931358','12931400','13778180','12931402','13505397',
            '12606969', '12965411', '12606971', '12966872', '13084144', '13144836',
            '14838692','10493939',
        ];
        $shop_ids2 = ['14838692',];
        $ele_shop_ids = ['2097599175','503080703','2097569592','2097564703','2097599173','503056324','2097564702',
            '503056325','2097569593','2097599174','503071024','1103516972','2130661395'];

        if (!empty($data)) {
            $data = array_chunk($data, 100);
            $meituan = app("minkang");
            $meiquan = app("meiquan");
            $ele = app("ele");
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    $stock_data_ele[] = $item->upc . ':' . $item->stock;
                }

                foreach ($shop_ids as $shop_id) {
                    foreach ($stock_data as $key => $stock_item) {
                        $stock_data[$key]['app_poi_code'] = $shop_id;
                    }
                    $params['app_poi_code'] = $shop_id;
                    $params['medicine_data'] = json_encode($stock_data);

                    $meituan->medicineStock($params);
                    // $res = $meituan->medicineStock($params);
                    // Log::info("万祥门店：{$shop_id}，同步库存返回结果", [$res]);
                }

                foreach ($shop_ids2 as $shop_id) {
                    foreach ($stock_data as $key => $stock_item) {
                        $stock_data[$key]['app_poi_code'] = $shop_id;
                    }
                    $params['app_poi_code'] = $shop_id;
                    $params['medicine_data'] = json_encode($stock_data);
                    $params['access_token'] = $meiquan->getShopToken($shop_id);

                    $meituan->medicineStock($params);
                }

                foreach ($ele_shop_ids as $ele_shop_id) {
                    $ele_params['shop_id'] = $ele_shop_id;
                    $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                    $ele->skuStockUpdate($ele_params);
                }
            }
        }
        $this->info('中心仓库存同步-结束......');
        Log::info('中心仓库存同步-结束......');

        // $this->info('门店「12606969」库存同步-开始......');
        // Log::info('门店「12606969」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0009' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     $meituan = app("minkang");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '12606969';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $meituan->medicineStock($params);
        //     }
        // }
        // $this->info('门店「12606969」库存同步-结束......');
        // Log::info('门店「12606969」库存同步-结束......');

        // // 12965411
        // $this->info('门店「12965411」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0004' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     $meituan = app("minkang");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '12965411';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $meituan->medicineStock($params);
        //     }
        // }
        // $this->info('门店「12965411」库存同步-结束......');

        // // 12606971
        // $this->info('门店「12606971」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0015' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     $meituan = app("minkang");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '12606971';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $meituan->medicineStock($params);
        //     }
        // }
        // $this->info('门店「12606971」库存同步-结束......');

        // // 12966872
        // $this->info('门店「12966872」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0012' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     $meituan = app("minkang");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '12966872';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $meituan->medicineStock($params);
        //     }
        // }
        // $this->info('门店「12966872」库存同步-结束......');

        // // 13084144
        // $this->info('门店「13084144」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0003' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     $meituan = app("minkang");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '13084144';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $meituan->medicineStock($params);
        //     }
        // }
        // $this->info('门店「13084144」库存同步-结束......');

        // // 13144836
        // $this->info('门店「13144836」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0006' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     $meituan = app("minkang");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '13144836';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $meituan->medicineStock($params);
        //     }
        // }
        // $this->info('门店「13144836」库存同步-结束......');

        // 14971401
        $this->info('门店「14971401」库存同步-开始......');
        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0017' AND [upc] <> '' AND [upc] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 100);
            $meituan = app("minkang");
            $ele = app("ele");
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    $stock_data_ele[] = $item->upc . ':' . $item->stock;
                }

                $params['app_poi_code'] = '14971401';
                $params['medicine_data'] = json_encode($stock_data);
                $meituan->medicineStock($params);

                $ele_params['shop_id'] = '1105417150';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                $ele->skuStockUpdate($ele_params);
            }
        }
        $this->info('门店「14971401」库存同步-结束......');
    }
}
