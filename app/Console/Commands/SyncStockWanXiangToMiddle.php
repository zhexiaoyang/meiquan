<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepot;
use App\Models\MedicineDepotCategory;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStockWanXiangToMiddle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-wanxiang-to-middle';

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
                'mqid' => 5976,
            ],
            [
                'name' => '万祥大药房（桃源路店）',
                'shopid' => '0011',
                // 'shopid' => '0007',
                'mtid' => '10493939',
                'eleid' => '503071024',
                'bind' => 31,
                'mqid' => 5072,
            ],
            [
                'name' => '万祥大药房（北京路店）',
                'shopid' => '0005',
                // 'shopid' => '0007',
                'mtid' => '14838692',
                'eleid' => '503056325',
                'bind' => 31,
                'mqid' => 2182,
            ],
            [
                'name' => '万祥大药房（厚德店）',
                'shopid' => '0009',
                'mtid' => '12606969',
                'eleid' => '2097599175',
                'bind' => 4,
                'mqid' => 5088,
            ],
            [
                'name' => '万祥大药房（江山店）',
                'shopid' => '0004',
                'mtid' => '12965411',
                'eleid' => '503080703',
                'bind' => 4,
                'mqid' => 3031,
            ],
            [
                'name' => '万祥大药房（光华路店）',
                'shopid' => '0001',
                'mtid' => '12931400',
                'eleid' => '2097569593',
                'bind' => 4,
                'mqid' => 3030,
            ],
            [
                'name' => '万祥大药房（临江店）',
                'shopid' => '0015',
                'mtid' => '12606971',
                'eleid' => '2097569592',
                'bind' => 4,
                'mqid' => 5089,
            ],
            [
                'name' => '万祥大药房（长春路店）',
                'shopid' => '0012',
                'mtid' => '12966872',
                'eleid' => '2097564703',
                'bind' => 4,
                'mqid' => 3033,
            ],
            [
                'name' => '万祥大药房（越山路店）',
                'shopid' => '0003',
                'mtid' => '13084144',
                'eleid' => '2097599173',
                'bind' => 4,
                'mqid' => 5092,
            ],
            [
                'name' => '万祥大药房（帕萨迪纳店）',
                'shopid' => '0002',
                'mtid' => '12931402',
                'eleid' => '2097599174',
                'bind' => 4,
                'mqid' => 3032,
            ],
            [
                'name' => '万祥大药房（光华路二店）',
                'shopid' => '0016',
                'mtid' => '13778180',
                'eleid' => '2130661395',
                'bind' => 4,
                'mqid' => 5087,
            ],
            [
                'name' => '万祥大药房（翠江锦苑店）',
                'shopid' => '0010',
                'mtid' => '13505397',
                'eleid' => '1103516972',
                'bind' => 4,
                'mqid' => 5090,
            ],
            [
                'name' => '万祥大药房（鞍山街店）',
                'shopid' => '0006',
                'mtid' => '13144836',
                'eleid' => '503056324',
                'bind' => 4,
                'mqid' => 5091,
            ],
            [
                'name' => '万祥大药房（珲春街店）',
                'shopid' => '0007',
                'mtid' => '12931358',
                'eleid' => '2097564702',
                'bind' => 4,
                'mqid' => 5080,
            ],
            [
                'name' => '万祥大药房（万馨店）',
                'shopid' => '0017',
                'mtid' => '14971401',
                'eleid' => '1105417150',
                'bind' => 31,
                'mqid' => 5073,
            ],
            [
                'name' => '万祥大药房（中海店）',
                'shopid' => '0019',
                'mtid' => '17080701',
                'eleid' => '509132636',
                'bind' => 31,
                'mqid' => 6188,
            ],
            [
                'name' => '万祥大药房（荣光分店）',
                'shopid' => '0020',
                'mtid' => '17080550',
                'eleid' => '1142788048',
                'bind' => 31,
                'mqid' => 6190,
            ],
            [
                'name' => '万祥大药房(公园道店)',
                'shopid' => '0021',
                'mtid' => '17624811',
                'eleid' => '1151186753',
                'bind' => 31,
                'mqid' => 6430,
            ],
            [
                'name' => '万祥大药房（天润城店）',
                'shopid' => '0022',
                'mtid' => '18158326',
                'eleid' => '1157836009',
                'bind' => 31,
                'mqid' => 6631,
            ],
        ];
        foreach ($shops as $shop) {
            $shop_name = $shop['name'];
            $shop_id = $shop['shopid'];
            $mqid = $shop['mqid'];
            $this->info("门店「{$shop_name}}:同步库存-开始......");
            Log::info("门店「{$shop_name}}:同步库存-开始......");
            try {
                $data = DB::connection('wanxiang_haidian')
                    ->select("SELECT 药品ID as id,名称 as name,规格 as spec,saleprice,进价 as cost,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$shop_id}' AND [upc] <> '' AND [upc] IS NOT NULL");
            } catch (\Exception $exception) {
                $data = [];
                $this->info("门店「{$shop_name}}:」数据查询报错......");
                Log::info("门店「{$shop_name}}:」数据查询报错......");
            }
            if (!empty($data)) {
                $this->info("门店「{$shop_name}}:」数据总数：".count($data));
                foreach ($data as $v) {
                    $upc = $v->upc;
                    $name = $v->name;
                    $price = $v->saleprice;
                    $cost = $v->cost;
                    $stock = $v->stock;
                    if (!$name || !$upc) {
                        continue;
                    }
                    $this->info("门店「{$shop_name}}:{$name}|{$upc}|---开始");
                    if (!Medicine::select('id')->where('shop_id', $mqid)->where('upc', $upc)->first()) {
                        $this->info("门店「{$shop_name}}:」数药品不存在|{$name}|{$upc}");
                        if ($depot = MedicineDepot::where('upc', $upc)->first()) {
                            $depot_category_ids = \DB::table('wm_depot_medicine_category')->where('medicine_id', $depot->id)->get()->pluck('category_id');
                            if (!empty($depot_category_ids)) {
                                // 根据查找的分类ID，查找该商品所有分类
                                $depot_categories = MedicineDepotCategory::whereIn('id', $depot_category_ids)->get();
                                if (!empty($depot_categories)) {
                                    foreach ($depot_categories as $depot_category) {
                                        $pid = 0;
                                        if ($depot_category->pid != 0) {
                                            if ($depot_category_parent = MedicineDepotCategory::find($depot_category->pid)) {
                                                try {
                                                    $category_parent = MedicineCategory::firstOrCreate(
                                                        [
                                                            'shop_id' => $mqid,
                                                            'name' => $depot_category_parent->name,
                                                        ],
                                                        [
                                                            'shop_id' => $mqid,
                                                            'pid' => 0,
                                                            'name' => $depot_category_parent->name,
                                                            'sort' => $depot_category_parent->sort,
                                                        ]
                                                    );
                                                } catch (\Exception $exception) {
                                                    $category_parent = MedicineCategory::where([
                                                        'shop_id' => $mqid,
                                                        'name' => $depot_category_parent->name,
                                                    ])->first();
                                                    \Log::info("创建分类失败(父级)|shop_id:{$mqid}|name:{$depot_category_parent->name}|重新查询结果：", [$category_parent]);
                                                }
                                                $pid = $category_parent->id;
                                            }
                                        }
                                        try {
                                            $c = MedicineCategory::firstOrCreate(
                                                [
                                                    'shop_id' => $mqid,
                                                    'name' => $depot_category->name,
                                                ],
                                                [
                                                    'shop_id' => $mqid,
                                                    'pid' => $pid,
                                                    'name' => $depot_category->name,
                                                    'sort' => $depot_category->sort,
                                                ]
                                            );
                                        } catch (\Exception $exception) {
                                            $c = MedicineCategory::where([
                                                'shop_id' => $mqid,
                                                'name' => $depot_category->name,
                                            ])->first();
                                            \Log::info("创建分类失败|shop_id:{$mqid}|name:{$depot_category->name}|重新查询结果：", [$c]);
                                        }
                                    }
                                }
                            }
                            $medicine_arr = [
                                'shop_id' => $mqid,
                                'name' => $depot->name,
                                'upc' => $depot->upc,
                                'cover' => $depot->cover,
                                'brand' => $depot->brand,
                                'spec' => $depot->spec,
                                'price' => 0,
                                'down_price' => $price,
                                'stock' => $stock,
                                'guidance_price' => $cost,
                                'depot_id' => $depot->id,
                            ];
                        } else {
                            $l = strlen($upc);
                            if ($l >= 7 && $l <= 19) {
                                $_depot = MedicineDepot::create([
                                    'name' => $name,
                                    'upc' => $upc
                                ]);
                                \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $_depot->id, 'category_id' => 215]);
                                // 创建-暂未分类
                                $c = MedicineCategory::firstOrCreate(
                                    [
                                        'shop_id' => $mqid,
                                        'name' => '暂未分类',
                                    ],
                                    [
                                        'shop_id' => $mqid,
                                        'pid' => 0,
                                        'name' => '暂未分类',
                                        'sort' => 1000,
                                    ]
                                );
                                $medicine_arr = [
                                    'shop_id' => $mqid,
                                    'name' => $name,
                                    'upc' => $upc,
                                    'brand' => '',
                                    'spec' => '',
                                    'price' => 0,
                                    'down_price' => $price,
                                    'stock' => $stock,
                                    'guidance_price' => $cost,
                                    'depot_id' => $_depot->id,
                                ];
                            }
                        }
                        if (isset($medicine_arr)) {
                            try {
                                $medicine = Medicine::create($medicine_arr);
                                \DB::table('wm_medicine_category')->insert(['medicine_id' => $medicine->id, 'category_id' => $c->id]);
                            } catch (\Exception $exception) {
                                // \Log::info("V2添加商品失败", [$exception->getMessage(), $exception->getLine(), $exception->getFile()]);
                            }
                        }
                    }
                }
            }
            $this->info("门店「{$shop_name}}:」同步库存-结束......");
            Log::info("门店「{$shop_name}}:」同步库存-结束......");
        }
        $this->info('------------万祥同步库存结束------------');
    }
}
