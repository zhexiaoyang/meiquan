<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCodeRuiZhiJia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-code-ruizhijia';

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
        $this->info('------------瑞之佳绑定编码------------');
        Log::info("------------瑞之佳绑定编码------------");

        $shangou = app("meiquan");
        // 闪购
        $shops_shangou = [
            '重庆市瑞之佳药房有限公司' => '15297836',
            '沙坪坝区瑞之佳药房彭可药店' => '12029100',
            '沙坪坝区瑞之佳药房翰林药店' => '15297787',
            '瑞之佳药房沙坪坝区彭勇药店' => '6066049',
            '重庆市瑞之佳药房有限公司听蓝湾店' => '6066051',
            '沙坪坝区瑞之佳天禧药房' => '12029007',
            '沙坪坝区瑞之佳药房好城时光店' => '6066047',
            '沙坪坝区瑞之佳药房回龙坝药店' => '15297294',
            '瑞之佳药房北碚区树鑫药店' => '15297459',
            '重庆市瑞之佳药房有限公司渝中区单巷子店' => '15404834',
            '重庆市瑞之佳药房有限公司界石店' => '15297795',
            '重庆市瑞之佳药房有限公司瑞集店' => '12029019',
            '重庆丰文瑞之佳药房' => '12029029',
            '重庆市瑞之佳药房有限公司第五分公司' => '15232184',
        ];

        foreach ($shops_shangou as $name => $id) {
            $this->info("门店「{$name}}:{$id}」绑定编码-开始......");
            Log::info("门店「{$name}}:{$id}」绑定编码-开始......");
            $data = DB::connection('ruizhijia')
                ->select("SELECT 商品条码 as upc FROM [dbo].[vZtGoods] WHERE [门店名称] = N'{$name}' AND [商品条码] <> '' AND [商品条码] IS NOT NULL");
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

                    $params['app_poi_code'] = $id;
                    $params['medicine_data'] = json_encode($code_data);
                    $params['access_token'] = $shangou->getShopToken($id);
                    $shangou->medicineCodeUpdate($params);
                }
            }
            $this->info("门店「{$name}}:{$id}」绑定编码-结束......");
            Log::info("门店「{$name}}:{$id}」绑定编码-结束......");
        }
    }
}
