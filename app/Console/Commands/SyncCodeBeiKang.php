<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCodeBeiKang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-code-beikang';

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
        // --------------------- 桐君阁大药房（北城天街店）:5910555 ---------------------
        $this->info('门店「桐君阁大药房（北城天街店）:5910555」编码绑定同步-开始......');
        Log::info('门店「桐君阁大药房（北城天街店）:5910555」编码绑定同步-开始......');
        // $data = DB::connection('wanxiang_haidian')
        //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0017' AND [upc] <> '' AND [upc] IS NOT NULL");
        $data = DB::connection('beikang')
            ->select("SELECT 门店商品编码 as id, 药品条形码 as upc FROM [dbo].[商品库存清单] WHERE [门店编码] = N'38店' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
        if (!empty($data)) {
            // $this->info('门店「桐君阁大药房（北城天街店）:5910555」不是空数据......');
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $code_data = [];
                foreach ($items as $item) {
                    $code_data[] = [
                        'upc' => trim($item->upc),
                        'app_medicine_code_new' => trim($item->id),
                    ];
                }

                // 绑定商品编码
                $params['app_poi_code'] = '5910555';
                $params['medicine_data'] = json_encode($code_data);
                $res = $minkang->medicineCodeUpdate($params);
                // Log::info('门店「桐君阁大药房（北城天街店）:5910555」编码绑定同步-结束......', [$res]);
            }
        } else {
            $this->info('门店「桐君阁大药房（北城天街店）:5910555」空数据......');
        }
        $this->info('门店「桐君阁大药房（北城天街店）:5910555」编码绑定同步-结束......');
        Log::info('门店「桐君阁大药房（北城天街店）:5910555」编码绑定同步-结束......');

        $shops = [
            '桐君阁大药房(湖云街店)' => '5910549',
            // '倍康大药房(新溉大道两江三店)' => '11888100',
            // '倍康大药房(两江四店)' => '11888102',
            '倍康大药房(渝铁家苑两江五店)' => '11888808',
            '倍康大药房(天宫殿街道合安药店)' => '11889492',
            '倍康大药房(海尔路175号店)' => '11657315',
            '倍康大药房(龙城天都店)' => '11633600',
            '倍康大药房(港城印象店)' => '11633605',
            '桐君阁大药房(恒康路店)' => '5910544',
            '倍康大药房(西郊支路店)' => '5910543',
        ];

        foreach ($shops as $name => $id) {
            $this->info("门店「{$name}}:{$id}」编码绑定同步-开始......");
            Log::info("门店「{$name}}:{$id}」编码绑定同步-开始......");
            // $data = DB::connection('wanxiang_haidian')
            //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0017' AND [upc] <> '' AND [upc] IS NOT NULL");
            $data = DB::connection('beikang')
                ->select("SELECT 门店商品编码 as id, 药品条形码 as upc FROM [dbo].[药品商品库存清单] WHERE [门店ID] = N'{$id}' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
            if (!empty($data)) {
                // $this->info('门店「桐君阁大药房（北城天街店）:5910555」不是空数据......');
                $data = array_chunk($data, 200);
                foreach ($data as $items) {
                    $code_data = [];
                    foreach ($items as $item) {
                        $code_data[] = [
                            'upc' => trim($item->upc),
                            'app_medicine_code_new' => trim($item->id),
                        ];
                    }

                    // 绑定商品编码
                    $params['app_poi_code'] = $id;
                    $params['medicine_data'] = json_encode($code_data);
                    $res = $minkang->medicineCodeUpdate($params);
                    // Log::info('门店「桐君阁大药房（北城天街店）:5910555」编码绑定同步-结束......', [$res]);
                }
            } else {
                $this->info("门店「{$name}}:{$id}」空数据......");
            }
            $this->info("门店「{$name}}:{$id}」编码绑定同步-结束......");
            Log::info("门店「{$name}}:{$id}」编码绑定同步-结束......");
        }
    }
}
