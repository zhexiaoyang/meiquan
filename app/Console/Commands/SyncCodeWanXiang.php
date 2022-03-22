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
        $meituan = app("minkang");
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' AND [upc] <> '' AND [upc] IS NOT NULL");
        //     // ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0007' and [upc] = '6948060300279'");
        // $shop_ids = ['12931358','12931400','13778180','12931402','13505397'];
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
        //     }
        // }

        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0009' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '12606969';
                $params['medicine_data'] = json_encode($code_data);
                $meituan->medicineCodeUpdate($params);
            }
        }
    }
}
