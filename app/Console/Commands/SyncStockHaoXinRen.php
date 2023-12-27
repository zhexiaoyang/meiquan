<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStockHaoXinRen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-haoxinren';

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
        $shops = [
            [
                'shop_id' => 1,
                'mtid' => '9096113',
                'type' => 31,
                'name' => '濮阳市好心人大药房连锁有限公司昆吾路店',
            ],
            // [
            //     'shop_id' => 2,
            //     'mtid' => '9097467',
            //     'type' => 31,
            //     'name' => '濮阳市好心人大药房连锁有限公司南海路店',
            // ],
            // [
            //     'shop_id' => 4,
            //     'mtid' => '9096053',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司盘锦路店',
            // ],
            // [
            //     'shop_id' => 5,
            //     'mtid' => '9096111',
            //     'type' => 31,
            //     'name' => '濮阳市好心人大药房连锁有限公司黄河东路店',
            // ],
            // [
            //     'shop_id' => 9,
            //     'mtid' => '9096112',
            //     'type' => 31,
            //     'name' => '濮阳市好心人大药房连锁有限公司中原路店',
            // ],
            // [
            //     'shop_id' => 10,
            //     'mtid' => '9096115',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司石化中路店',
            // ],
            // [
            //     'shop_id' => 11,
            //     'mtid' => '9096114',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司石化路店',
            // ],
            // [
            //     'shop_id' => 13,
            //     'mtid' => '9097465',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司濮阳县国庆路店',
            // ],
            // [
            //     'shop_id' => 15,
            //     'mtid' => '9097468',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司扶余路店',
            // ],
            // [
            //     'shop_id' => 21,
            //     'mtid' => '9096049',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司苏北路店',
            // ],
            // [
            //     'shop_id' => 24,
            //     'mtid' => '9096048',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司盘锦中路店',
            // ],
            // [
            //     'shop_id' => 26,
            //     'mtid' => '9096050',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司世纪景园店',
            // ],
            // [
            //     'shop_id' => 27,
            //     'mtid' => '9188581',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司华府山水店',
            // ],
            // [
            //     'shop_id' => 31,
            //     'mtid' => '11281592',
            //     'type' => 31,
            //     'name' => '濮阳市好心人大药房连锁有限公司开德路店',
            // ],
            // [
            //     'shop_id' => 32,
            //     'mtid' => '11293334',
            //     'type' => 31,
            //     'name' => '濮阳市好心人大药房连锁有限公司中原路旗舰店',
            // ],
            // [
            //     'shop_id' => 37,
            //     'mtid' => '13891047',
            //     'type' => 4,
            //     'name' => '濮阳市好心人大药房连锁有限公司五一路店',
            // ],
        ];

        $this->info('------------好心人同步库存开始------------');
        foreach ($shops as $shop) {
            $name = $shop['name'];
            $shop_id = $shop['shop_id'];
            $mt_id = $shop['mtid'];
            $bind = $shop['type'];
            $this->info("门店「{$name}}:{$mt_id}」同步库存-开始......");
            Log::info("门店「{$name}}:{$mt_id}」同步库存-开始......");
            $minkang = app("minkang");
            $meiquan = app("meiquan");
            try {
                $data = DB::connection('haoxinren')
                    ->select("SELECT 商品编号 as id,条形码 as upc,库存总数量 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$shop_id}' AND [upc] <> '' AND [upc] IS NOT NULL GROUP BY [upc],[药品ID],[库存]");
            } catch (\Exception $exception) {
                $data = [];
                $this->info("门店「{$name}}:{$mt_id}」数据查询报错......");
                Log::info("门店「{$name}}:{$mt_id}」数据查询报错......");
            }
            if (!empty($data)) {
                $data = array_chunk($data, 100);
                foreach ($data as $items) {
                    $stock_data = [];
                    $upc_data = [];
                    foreach ($items as $item) {
                        if (in_array($item->upc, $upc_data)) {
                            continue;
                        }
                        $stock = (int) $item->stock;
                        $stock = $stock >= 0 ? $stock : 0;
                        $stock_data[] = [
                            'app_medicine_code' => $item->id,
                            'stock' => $stock,
                        ];
                        $upc_data[] = $item->upc;
                    }

                    $params['app_poi_code'] = $mt_id;
                    $params['medicine_data'] = json_encode($stock_data);
                    if ($bind === 4) {
                        $minkang->medicineStock($params);
                    } else {
                        $params['access_token'] = $meiquan->getShopToken($mt_id);
                        $mtres = $meiquan->medicineStock($params);
                        Log::info("好心人日志美团门店「{$name}}:{$mt_id}」同步库存-请求参数", $stock_data);
                        Log::info("好心人日志美团门店「{$name}}:{$mt_id}」同步库存-结果", [$mtres]);
                    }
                }
            }
            $this->info("门店「{$name}}:{$mt_id}」同步库存-结束......");
            Log::info("门店「{$name}}:{$mt_id}」同步库存-结束......");
            unset($upcs);
        }
        $this->info('------------好心人同步库存结束------------');
    }
}
