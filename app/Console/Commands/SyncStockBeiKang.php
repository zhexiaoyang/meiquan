<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStockBeiKang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-beikang';

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
        $minkang = app("minkang");
        $shangou = app("meiquan");

        // // --------------------- 桐君阁大药房（北城天街店）:5910555 ---------------------
        // $this->info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-开始......');
        // Log::info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-开始......');
        // $data = DB::connection('beikang')
        //     ->select("SELECT 门店商品编码 as id, 库存 as stock FROM [dbo].[商品库存清单] WHERE [门店编码] = N'38店' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 200);
        //     foreach ($data as $items) {
        //         $stock_data = [];
        //         foreach ($items as $item) {
        //             $stock_data[] = [
        //                 'app_medicine_code' => trim($item->id),
        //                 'stock' => (int) $item->stock,
        //             ];
        //         }
        //
        //         $params['app_poi_code'] = '5910555';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $minkang->medicineStock($params);
        //     }
        // }
        // $this->info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-结束......');
        // Log::info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-结束......');

        $shops = [
            '桐君阁大药房（北城天街店）' => '5910555',
            '太极大药房（杨河二村店）' => '5910541',
            '桐君阁大药房（北城四路店）' => '5910542',
            '倍康大药房（凤澜路店）' => '5910545',
            '倍康大药房（金山路店）' => '5910547',
            '桐君阁大药房（泰山大道店）' => '5910552',
            '桐君阁大药房（新溉路店）' => '5910553',
            '桐君阁大药房（渝鲁大道店）' => '5910696',
            '倍康大药房（龙城天都店）' => '11633600',
            '倍康大药房（海尔路175号店）' => '11657315',
            '倍康大药房（鲁能星城13街区合亮店）' => '11888091',
            '倍康大药房（渝铁家苑两江五店）' => '11888808',
            '倍康大药房（两江二店）' => '11888809',
            '倍康大药房（泰山大道合济店）' => '11889377',
            '倍康大药房（鲁能星城十二街区合鲁店）' => '11889378',
            '倍康大药房（鸿恩四路合绪店）' => '11889436',
            '倍康大药房（天宫殿街道合安药店）' => '11889492',
            '倍康大药房（渝北一店）' => '11889497',
            '桐君阁大药房（五里店36店）' => '11889581',
        ];
        $shops2 = [
            '倍康大药房（建东一店）' => '11889499',
            '倍康大药房（鸿恩二路合胜店）' => '11888095',
            '倍康大药房（建新西路合聚店）' => '11888097',
            '倍康大药房（茅溪路合澜店）' => '11888099',
            '倍康大药房（新溉大道两江三店）' => '11888100',
            '倍康大药房（两江四店）' => '11888102',
            '倍康大药房（鲁能一店）' => '11888105',
            '倍康大药房（明珠馨园合通店）' => '11889375',
            '倍康大药房（央著天域合鸥店）' => '11889494',
            '桐君阁大药房（合耀大药店）' => '11889579',
            '倍康大药房（嘉华路隆达嘉苑合霞店）' => '11889583',
        ];

        $hour = (int) date("H");

        if ($hour >= 8 && $hour < 22) {
            $this->info("门店同步......");
            Log::info("门店同步......");
            foreach ($shops as $name => $id) {
                $this->info("门店「{$name}}:{$id}」库存同步-开始......");
                Log::info("门店「{$name}}:{$id}」库存同步-开始......");
                try {
                    $data = DB::connection('beikang')
                        ->select("SELECT 商品自编码 as id, 库存 as stock FROM [dbo].[药品商品库存清单] WHERE [门店ID] = N'{$id}' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
                } catch (\Exception $exception) {
                    continue;
                }
                if (!empty($data)) {
                    $data = array_chunk($data, 200);
                    foreach ($data as $items) {
                        $stock_data = [];
                        foreach ($items as $item) {
                            $stock_data[] = [
                                'app_medicine_code' => trim($item->id),
                                'stock' => (int) $item->stock,
                            ];
                        }

                        $params['app_poi_code'] = $id;
                        $params['medicine_data'] = json_encode($stock_data);
                        $minkang->medicineStock($params);
                    }
                }
                $this->info("门店「{$name}}:{$id}」库存同步-结束......");
                Log::info("门店「{$name}}:{$id}」库存同步-结束......");
            }

            foreach ($shops2 as $name => $id) {
                $this->info("门店「{$name}}:{$id}」库存同步-开始......");
                Log::info("门店「{$name}}:{$id}」库存同步-开始......");
                try {
                    $data = DB::connection('beikang')
                        ->select("SELECT 商品自编码 as id, 库存 as stock FROM [dbo].[药品商品库存清单] WHERE [门店ID] = N'{$id}' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
                } catch (\Exception $exception) {
                    continue;
                }
                if (!empty($data)) {
                    $data = array_chunk($data, 200);
                    foreach ($data as $items) {
                        $stock_data = [];
                        foreach ($items as $item) {
                            $stock_data[] = [
                                'app_medicine_code' => trim($item->id),
                                'stock' => (int) $item->stock,
                            ];
                        }

                        $params['app_poi_code'] = $id;
                        $params['medicine_data'] = json_encode($stock_data);
                        $params['access_token'] = $shangou->getShopToken($id);
                        $shangou->medicineStock($params);
                    }
                }
                $this->info("门店「{$name}}:{$id}」库存同步-结束......");
                Log::info("门店「{$name}}:{$id}」库存同步-结束......");
            }
        } else {
            $this->info("仓库同步......");
            Log::info("仓库同步......");
            try {
                $data = DB::connection('beikang')
                    ->select("SELECT 商品自编码 as id, 库存 as stock FROM [dbo].[药品商品库存清单] WHERE [门店ID] = N'11889499' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
            } catch (\Exception $exception) {
                $data = [];
            }
            if (!empty($data)) {
                $data = array_chunk($data, 200);
                foreach ($data as $items) {
                    $stock_data = [];
                    foreach ($items as $item) {
                        $stock_data[] = [
                            'app_medicine_code' => trim($item->id),
                            'stock' => (int) $item->stock,
                        ];
                    }
                    foreach ($shops as $name => $id) {
                        $this->info("仓库同步|民康|门店「{$name}}:{$id}」库存同步-开始......");
                        Log::info("仓库同步|民康|门店「{$name}}:{$id}」库存同步-开始......");
                        $params['app_poi_code'] = $id;
                        $params['medicine_data'] = json_encode($stock_data);
                        $minkang->medicineStock($params);
                    }
                    foreach ($shops2 as $name => $id) {
                        $this->info("仓库同步|闪购|门店「{$name}}:{$id}」库存同步-开始......");
                        Log::info("仓库同步|闪购|门店「{$name}}:{$id}」库存同步-开始......");
                        $params['app_poi_code'] = $id;
                        $params['medicine_data'] = json_encode($stock_data);
                        $params['access_token'] = $shangou->getShopToken($id);
                        $shangou->medicineStock($params);
                    }
                }
            }
        }


    }
}
