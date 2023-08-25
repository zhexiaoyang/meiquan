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
        $shops = [
            [
                'name' => '万祥大药房（未来公馆店）',
                'shopid' => '0018',
                'mtid' => '16297828',
                'eleid' => '1129069917',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（桃源路店）',
                'shopid' => '0011',
                // 'shopid' => '0007',
                'mtid' => '10493939',
                'eleid' => '503071024',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（北京路店）',
                'shopid' => '0005',
                // 'shopid' => '0007',
                'mtid' => '14838692',
                'eleid' => '503056325',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（厚德店）',
                'shopid' => '0009',
                'mtid' => '12606969',
                'eleid' => '2097599175',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（江山店）',
                'shopid' => '0004',
                'mtid' => '12965411',
                'eleid' => '503080703',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（光华路店）',
                'shopid' => '0001',
                'mtid' => '12931400',
                'eleid' => '2097569593',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（临江店）',
                'shopid' => '0015',
                'mtid' => '12606971',
                'eleid' => '2097569592',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（长春路店）',
                'shopid' => '0012',
                'mtid' => '12966872',
                'eleid' => '2097564703',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（越山路店）',
                'shopid' => '0003',
                'mtid' => '13084144',
                'eleid' => '2097599173',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（帕萨迪纳店）',
                'shopid' => '0002',
                'mtid' => '12931402',
                'eleid' => '2097599174',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（光华路二店）',
                'shopid' => '0016',
                'mtid' => '13778180',
                'eleid' => '2130661395',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（翠江锦苑店）',
                'shopid' => '0010',
                'mtid' => '13505397',
                'eleid' => '1103516972',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（鞍山街店）',
                'shopid' => '0006',
                'mtid' => '13144836',
                'eleid' => '503056324',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（珲春街店）',
                'shopid' => '0007',
                'mtid' => '12931358',
                'eleid' => '2097564702',
                'bind' => 4,
            ],
            [
                'name' => '万祥大药房（万馨店）',
                'shopid' => '0017',
                'mtid' => '14971401',
                'eleid' => '1105417150',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（中海店）',
                'shopid' => '0019',
                'mtid' => '17080701',
                'eleid' => '509132636',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（荣光分店）',
                'shopid' => '0020',
                'mtid' => '17080550',
                'eleid' => '1142788048',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（天润城店）',
                'shopid' => '0022',
                'mtid' => '18158326',
                'eleid' => '1157836009',
                'bind' => 31,
            ],
        ];
        foreach ($shops as $shop) {
            $name = $shop['name'];
            $shop_id = $shop['shopid'];
            $mt_id = $shop['mtid'];
            $ele_id = $shop['eleid'];
            $bind = $shop['bind'];
            $this->info("门店「{$name}}:{$mt_id}」同步库存-开始......");
            Log::info("门店「{$name}}:{$mt_id}」同步库存-开始......");
            $minkang = app("minkang");
            $meiquan = app("meiquan");
            $ele = app("ele");
            try {
                $data = DB::connection('wanxiang_haidian')
                    ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$shop_id}' AND [upc] <> '' AND [upc] IS NOT NULL GROUP BY [upc],[药品ID],[库存]");
            } catch (\Exception $exception) {
                $data = [];
                $this->info("门店「{$name}}:{$mt_id}」数据查询报错......");
                Log::info("门店「{$name}}:{$mt_id}」数据查询报错......");
            }
            if (!empty($data)) {
                $data = array_chunk($data, 50);
                foreach ($data as $items) {
                    $stock_data = [];
                    $stock_data_ele = [];
                    // $log_off = false;
                    foreach ($items as $item) {
                        // if ($item->id == '00723' && $mt_id = '16297828') {
                        //     $log_off = true;
                        // }
                        $stock = (int) $item->stock;
                        $stock = $stock >= 0 ? $stock : 0;
                        $stock_data[] = [
                            'app_medicine_code' => $item->id,
                            'stock' => $stock,
                        ];
                        $stock_data_ele[] = $item->upc . ':' . $stock;
                    }

                    $params['app_poi_code'] = $mt_id;
                    $params['medicine_data'] = json_encode($stock_data);
                    if ($bind === 4) {
                        $minkang->medicineStock($params);
                    } else {
                        $params['access_token'] = $meiquan->getShopToken($mt_id);
                        $mtres = $meiquan->medicineStock($params);
                        Log::info("万祥日志美团门店「{$name}}:{$mt_id}」同步库存-请求参数", $stock_data);
                        Log::info("万祥日志美团门店「{$name}}:{$mt_id}」同步库存-结果", [$mtres]);
                        // $res = $meiquan->medicineStock($params);
                        // if ($log_off) {
                        //     Log::info("loglogloglog", [$res]);
                        // }
                    }

                    $ele_params['shop_id'] = $ele_id;
                    $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                    // $ele->skuStockUpdate($ele_params);
                    $eleres = $ele->skuStockUpdate($ele_params);
                    Log::info("万祥日志饿了么门店「{$name}}:{$mt_id}」同步库存-请求参数", $stock_data_ele);
                    Log::info("万祥日志饿了么门店「{$name}}:{$mt_id}」同步库存-结果", [$eleres]);
                }
            }
            $this->info("门店「{$name}}:{$mt_id}」同步库存-结束......");
            Log::info("门店「{$name}}:{$mt_id}」同步库存-结束......");
        }
        $this->info('------------万祥同步库存结束------------');


        // $this->info('------------万祥同步库存开始------------');
        // $this->info('中心仓库存同步-开始......');
        // Log::info('中心仓库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' AND [upc] <> '' AND [upc] IS NOT NULL");
        //     // ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' and [upc] = '6948060300279'");
        // // $shop_ids = ['12931358','12931400','13778180','12931402','13505397'];
        // $shop_ids = ['12931358','12931400','13778180','12931402','13505397',
        //     '12606969', '12965411', '12606971', '12966872', '13084144', '13144836',
        //     '14838692','10493939',
        // ];
        // $shop_ids2 = ['14838692','10493939'];
        // $ele_shop_ids = ['2097599175','503080703','2097569592','2097564703','2097599173','503056324','2097564702',
        //     '503056325','2097569593','2097599174','503071024','1103516972','2130661395'];
        //
        // if (!empty($data)) {
        //     $data = array_chunk($data, 100);
        //     $meituan = app("minkang");
        //     $meiquan = app("meiquan");
        //     $ele = app("ele");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         $stock_data_ele = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //             $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
        //         }
        //
        //         foreach ($shop_ids as $shop_id) {
        //             foreach ($stock_data as $key => $stock_item) {
        //                 $stock_data[$key]['app_poi_code'] = $shop_id;
        //             }
        //             $params['app_poi_code'] = $shop_id;
        //             $params['medicine_data'] = json_encode($stock_data);
        //
        //             $meituan->medicineStock($params);
        //             // $res = $meituan->medicineStock($params);
        //             // Log::info("万祥门店：{$shop_id}，同步库存返回结果", [$res]);
        //         }
        //
        //         foreach ($shop_ids2 as $shop_id) {
        //             foreach ($stock_data as $key => $stock_item) {
        //                 $stock_data[$key]['app_poi_code'] = $shop_id;
        //             }
        //             $params['app_poi_code'] = $shop_id;
        //             $params['medicine_data'] = json_encode($stock_data);
        //             $params['access_token'] = $meiquan->getShopToken($shop_id);
        //
        //             $meiquan->medicineStock($params);
        //         }
        //
        //         foreach ($ele_shop_ids as $ele_shop_id) {
        //             $ele_params['shop_id'] = $ele_shop_id;
        //             $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
        //             $ele->skuStockUpdate($ele_params);
        //         }
        //     }
        // }
        // $this->info('中心仓库存同步-结束......');
        // Log::info('中心仓库存同步-结束......');

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
        // $this->info('门店「14971401」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0017' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 100);
        //     $meituan = app("meiquan");
        //     $ele = app("ele");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         $stock_data_ele = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //             $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
        //         }
        //
        //         $params['app_poi_code'] = '14971401';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $params['access_token'] = $meiquan->getShopToken('14971401');
        //         $meituan->medicineStock($params);
        //
        //         $ele_params['shop_id'] = '1105417150';
        //         $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
        //         $ele->skuStockUpdate($ele_params);
        //     }
        // }
        // $this->info('门店「14971401」库存同步-结束......');

        // 10493939
        // $this->info('门店「10493939」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0011' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 100);
        //     $meiquan = app("meiquan");
        //     $ele = app("ele");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         $stock_data_ele = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //             $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
        //         }
        //
        //         $params['app_poi_code'] = '10493939';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $params['access_token'] = $meiquan->getShopToken('10493939');
        //         $meiquan->medicineStock($params);
        //
        //         $ele_params['shop_id'] = '503071024';
        //         $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
        //         $ele->skuStockUpdate($ele_params);
        //     }
        // }
        // $this->info('门店「10493939」库存同步-结束......');

        // 万祥大药房（未来公馆店）16297828
        // $this->info('门店「16297828」库存同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0018' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 100);
        //     $meituan = app("meiquan");
        //     $ele = app("ele");
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         $stock_data_ele = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => $item->id,
        //                 'stock' => (int) $item->stock,
        //             ];
        //             $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
        //         }
        //
        //         $params['app_poi_code'] = '16297828';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $params['access_token'] = $meiquan->getShopToken('16297828');
        //         // $meituan->medicineStock($params);
        //         $res = $meituan->medicineStock($params);
        //         Log::info("1629782816297828mt", [$res]);
        //
        //         $ele_params['shop_id'] = '1129069917';
        //         $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
        //         // $ele->skuStockUpdate($ele_params);
        //         $res = $ele->skuStockUpdate($ele_params);
        //         Log::info("1629782816297828ele", [$res]);
        //     }
        // }
        // $this->info('门店「16297828」库存同步-结束......');
    }
}
