<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCodeWanXiang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-code-wanxiang';

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
        $this->info('------------万祥绑定编码开始------------');
        $shops = [
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
                'name' => '万祥大药房（桃源路店）',
                'shopid' => '0011',
                'mtid' => '10493939',
                'eleid' => '503071024',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（北京路店）',
                'shopid' => '0005',
                'mtid' => '14838692',
                'eleid' => '503056325',
                'bind' => 31,
            ],
            [
                'name' => '万祥大药房（未来公馆店）',
                'shopid' => '0018',
                'mtid' => '16297828',
                'eleid' => '1129069917',
                'bind' => 31,
            ],
        ];
        foreach ($shops as $shop) {
            $name = $shop['name'];
            $shop_id = $shop['shopid'];
            $mt_id = $shop['mtid'];
            $bind = $shop['bind'];
            $this->info("门店「{$name}}:{$mt_id}」绑定编码-开始......");
            Log::info("门店「{$name}}:{$mt_id}」绑定编码-开始......");
            $minkang = app("minkang");
            $meiquan = app("meiquan");
            $data = DB::connection('wanxiang_haidian')
                ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$shop_id}' AND [upc] <> '' AND [upc] IS NOT NULL");
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $code_data = [];
                foreach ($items as $item) {
                    $code_data[] = [
                        'upc' => $item->upc,
                        'app_medicine_code_new' => $item->upc,
                    ];
                }

                $params['app_poi_code'] = $mt_id;
                $params['medicine_data'] = json_encode($code_data);
                if ($bind === 4) {
                    $minkang->medicineCodeUpdate($params);
                } else {
                    $params['access_token'] = $meiquan->getShopToken($shop_id);
                    $meiquan->medicineCodeUpdate($params);
                }
            }
            $this->info("门店「{$name}}:{$mt_id}」绑定编码-结束......");
            Log::info("门店「{$name}}:{$mt_id}」绑定编码-结束......");
        }
        $this->info('------------万祥绑定编码结束------------');
        // $this->info('中心仓编码绑定同步-开始......');
        // Log::info('中心仓编码绑定同步-开始......');
        // $meituan = app("minkang");
        // $meiquan = app("meiquan");
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' AND [upc] <> '' AND [upc] IS NOT NULL");
        //     // ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' and [upc] = '6948060300279'");
        // // $shop_ids = ['12931358','12931400','13778180','12931402','13505397'];
        // $shop_ids = ['12931358','12931400','13778180','12931402','13505397',
        //     '12606969', '12965411', '12606971', '12966872', '13084144', '13144836',
        // ];
        // $shop_ids2 = ['14838692','10493939'];
        //
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         foreach ($shop_ids as $shop_id) {
        //             $params['app_poi_code'] = $shop_id;
        //             $params['medicine_data'] = json_encode($code_data);
        //
        //             $meituan->medicineCodeUpdate($params);
        //             // $res = $meituan->medicineCodeUpdate($params);
        //             // Log::info("万祥门店：{$shop_id}，绑定编码返回结果", [$res]);
        //         }
        //
        //         // 绑定商品编码
        //         foreach ($shop_ids2 as $shop_id) {
        //             $params['app_poi_code'] = $shop_id;
        //             $params['medicine_data'] = json_encode($code_data);
        //             $params['access_token'] = $meiquan->getShopToken($shop_id);
        //
        //             $meiquan->medicineCodeUpdate($params);
        //             // $res = $meituan->medicineCodeUpdate($params);
        //             // Log::info("万祥门店：{$shop_id}，绑定编码返回结果", [$res]);
        //         }
        //     }
        // }
        // $this->info('中心仓编码绑定同步-结束......');
        // Log::info('中心仓编码绑定同步-结束......');

        // $this->info('门店「12606969」编码绑定同步-开始......');
        // Log::info('门店「12606969」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0009' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '12606969';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「12606969」编码绑定同步-结束......');
        // Log::info('门店「12606969」编码绑定同步-结束......');
        //
        // // 12965411
        // $this->info('门店「12965411」编码绑定同步-开始......');
        // Log::info('门店「12965411」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0004' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '12965411';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「12965411」编码绑定同步-结束......');
        // Log::info('门店「12965411」编码绑定同步-结束......');
        //
        // // 12606971
        // $this->info('门店「12606971」编码绑定同步-开始......');
        // Log::info('门店「12606971」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0015' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '12606971';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「12606971」编码绑定同步-结束......');
        // Log::info('门店「12606971」编码绑定同步-结束......');
        //
        // // 12966872
        // $this->info('门店「12966872」编码绑定同步-开始......');
        // Log::info('门店「12966872」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0012' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '12966872';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「12966872」编码绑定同步-结束......');
        // Log::info('门店「12966872」编码绑定同步-结束......');
        //
        // // 13084144
        // $this->info('门店「13084144」编码绑定同步-开始......');
        // Log::info('门店「13084144」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0003' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '13084144';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「13084144」编码绑定同步-结束......');
        // Log::info('门店「13084144」编码绑定同步-结束......');
        //
        // // 13144836
        // $this->info('门店「13144836」编码绑定同步-开始......');
        // Log::info('门店「13144836」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0006' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '13144836';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「13144836」编码绑定同步-结束......');
        // Log::info('门店「13144836」编码绑定同步-结束......');

        // 14971401
        $this->info('门店「14971401」编码绑定同步-开始......');
        Log::info('门店「14971401」编码绑定同步-开始......');
        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0017' AND [upc] <> '' AND [upc] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $code_data = [];
                foreach ($items as $item) {
                    $code_data[] = [
                        'upc' => $item->upc,
                        'app_medicine_code_new' => $item->upc,
                    ];
                }

                // 绑定商品编码
                $params['app_poi_code'] = '14971401';
                $params['medicine_data'] = json_encode($code_data);
                $params['access_token'] = $meiquan->getShopToken('14971401');
                $meiquan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「14971401」编码绑定同步-结束......');
        Log::info('门店「14971401」编码绑定同步-结束......');

        // 10493939
        // $this->info('门店「10493939」编码绑定同步-开始......');
        // Log::info('门店「10493939」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0011' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '10493939';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $params['access_token'] = $meiquan->getShopToken('10493939');
        //         $meiquan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「10493939」编码绑定同步-结束......');
        // Log::info('门店「10493939」编码绑定同步-结束......');

        // 万祥大药房（未来公馆店）16297828
        // $this->info('门店「16297828」编码绑定同步-开始......');
        // Log::info('门店「16297828」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0018' AND [upc] <> '' AND [upc] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $code_data = [];
        //         foreach ($items as $item) {
        //             $code_data[] = [
        //                 'upc' => $item->upc,
        //                 'app_medicine_code_new' => $item->id,
        //             ];
        //         }
        //
        //         // 绑定商品编码
        //         $params['app_poi_code'] = '16297828';
        //         $params['medicine_data'] = json_encode($code_data);
        //         $params['access_token'] = $meiquan->getShopToken('16297828');
        //         $meituan->medicineCodeUpdate($params);
        //     }
        // }
        // $this->info('门店「16297828」编码绑定同步-结束......');
        // Log::info('门店「16297828」编码绑定同步-结束......');
    }
}
