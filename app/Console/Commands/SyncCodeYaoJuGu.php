<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCodeYaoJuGu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-code-yaojugu';

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
     */
    public function handle()
    {
        $this->info('------------药聚谷绑定编码------------');

        $minkang = app("minkang");
        $shangou = app("meiquan");
        // 闪购
        $shops_shangou = [
            '药聚谷药房（金科空港城店）' => '14539717',
            '药聚谷药房（礼仁店）' => '14540457',
            '药聚谷药房（丛岩店）' => '15214945',
            '药聚谷药房（翠渝路店）' => '15215557',
            '药聚谷药店（金色阳光店）' => '15215561',
            '药聚谷药店（嘉悦山水店）' => '15215569',
            '药聚谷药房（丽景天成店）' => '15215573',
            '药聚谷药店（空港佳园店）' => '15214944',
            '药聚谷药店（观月北路店）' => '15215564',
            '药聚谷药店（湖山花园店）' => '15215568',
            '药聚谷药房（天府丽正四期店）' => '15215572',
            '药聚谷药店（叠彩城店）' => '14439591',
            '药聚谷药房（廊桥水岸店）' => '14539179',
            '药聚谷药店（空港明珠店）' => '15214943',
            '药聚谷药房（巴蜀丽景店）' => '15215563',
            '药聚谷药房（兰花店）' => '15215567',
            '药聚谷药店（红石店）' => '15215575',
            '药聚谷药房（沿溪二路店）' => '14540282',
            '药聚谷药店（水语店）' => '14540942',
            '药聚谷药店（公园北路店）' => '15214946',
            '药聚谷药房（金桂花园店）' => '15215566',
        ];
        // 民康
        $shops_minkang = [
            '药聚谷药店(金竹路店)' => '8996957',
        ];

        foreach ($shops_minkang as $name => $id) {
            $this->info("门店「{$name}}:{$id}」库存同步-开始......");
            Log::info("门店「{$name}}:{$id}」库存同步-开始......");
            $data = DB::connection('yaojugu')
                ->select("SELECT 商品编码 as id, 商品条形码 as upc FROM [dbo].[v_busi_store] WHERE [美团门店ID] = N'{$id}' AND [商品条形码] <> '' AND [商品条形码] IS NOT NULL");
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

                    $params['app_poi_code'] = $id;
                    $params['medicine_data'] = json_encode($code_data);
                    $minkang->medicineCodeUpdate($params);
                }
            }
            $this->info("门店「{$name}}:{$id}」库存同步-结束......");
            Log::info("门店「{$name}}:{$id}」库存同步-结束......");
        }

        foreach ($shops_shangou as $name => $id) {
            $this->info("门店「{$name}}:{$id}」库存同步-开始......");
            Log::info("门店「{$name}}:{$id}」库存同步-开始......");
            $data = DB::connection('yaojugu')
                ->select("SELECT 商品编码 as id, 商品条形码 as upc FROM [dbo].[v_busi_store] WHERE [美团门店ID] = N'{$id}' AND [商品条形码] <> '' AND [商品条形码] IS NOT NULL");
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

                    $params['app_poi_code'] = $id;
                    $params['medicine_data'] = json_encode($code_data);
                    $params['access_token'] = $shangou->getShopToken($id);
                    $shangou->medicineCodeUpdate($params);
                }
            }
            $this->info("门店「{$name}}:{$id}」库存同步-结束......");
            Log::info("门店「{$name}}:{$id}」库存同步-结束......");
        }
    }
}
