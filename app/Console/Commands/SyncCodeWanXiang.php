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
        $this->info('------------万祥同步库存开始------------');
        $this->info('中心仓编码绑定同步-开始......');
        Log::info('中心仓编码绑定同步-开始......');
        $meituan = app("minkang");
        $meiquan = app("meiquan");
        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' AND [upc] <> '' AND [upc] IS NOT NULL");
            // ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' and [upc] = '6948060300279'");
        // $shop_ids = ['12931358','12931400','13778180','12931402','13505397'];
        $shop_ids = ['12931358','12931400','13778180','12931402','13505397',
            '12606969', '12965411', '12606971', '12966872', '13084144', '13144836',
            '10493939',
        ];
        $shop_ids2 = ['14838692',];

        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $code_data = [];
                foreach ($items as $item) {
                    $code_data[] = [
                        'upc' => $item->upc,
                        'app_medicine_code_new' => $item->id,
                    ];
                }

                // 绑定商品编码
                foreach ($shop_ids as $shop_id) {
                    $params['app_poi_code'] = $shop_id;
                    $params['medicine_data'] = json_encode($code_data);

                    $meituan->medicineCodeUpdate($params);
                    // $res = $meituan->medicineCodeUpdate($params);
                    // Log::info("万祥门店：{$shop_id}，绑定编码返回结果", [$res]);
                }

                // 绑定商品编码
                foreach ($shop_ids2 as $shop_id) {
                    $params['app_poi_code'] = $shop_id;
                    $params['medicine_data'] = json_encode($code_data);
                    $params['access_token'] = $meiquan->getShopToken($shop_id);

                    $meiquan->medicineCodeUpdate($params);
                    // $res = $meituan->medicineCodeUpdate($params);
                    // Log::info("万祥门店：{$shop_id}，绑定编码返回结果", [$res]);
                }
            }
        }
        $this->info('中心仓编码绑定同步-结束......');
        Log::info('中心仓编码绑定同步-结束......');

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
                        'app_medicine_code_new' => $item->id,
                    ];
                }

                // 绑定商品编码
                $params['app_poi_code'] = '14971401';
                $params['medicine_data'] = json_encode($code_data);
                $meituan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「14971401」编码绑定同步-结束......');
        Log::info('门店「14971401」编码绑定同步-结束......');
    }
}
