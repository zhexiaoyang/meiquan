<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStockXueSong extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-xuesong';

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
        $this->info('------------雪松同步库存开始------------');;
        $minkang = app("minkang");
        $ele = app("ele");

        // --------------------- 雪松青年桥店:9493159 ---------------------
        $this->info('门店「雪松青年桥店:9493159」库存同步-开始......');
        Log::info('门店「雪松青年桥店:9493159」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493159' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693594', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493159';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693594';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松青年桥店:9493159」库存同步-结束......');
        Log::info('门店「雪松青年桥店:9493159」库存同步-结束......');

        // --------------------- 雪松站前店:9493161 ---------------------
        $this->info('门店「雪松站前店:9493161」库存同步-开始......');
        Log::info('门店「雪松站前店:9493161」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493161' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693595', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493161';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693595';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松站前店:9493161」库存同步-结束......');
        Log::info('门店「雪松站前店:9493161」库存同步-结束......');

        // --------------------- 雪松金山店:9493163 ---------------------
        $this->info('门店「雪松金山店:9493163」库存同步-开始......');
        Log::info('门店「雪松金山店:9493163」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493163' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693596', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493163';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693596';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松金山店:9493163」库存同步-结束......');
        Log::info('门店「雪松金山店:9493163」库存同步-结束......');

        // --------------------- 雪松湖西店:9493216 ---------------------
        $this->info('门店「雪松湖西店:9493216」库存同步-开始......');
        Log::info('门店「雪松湖西店:9493216」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493216' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693597', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493216';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693597';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松湖西店:9493216」库存同步-结束......');
        Log::info('门店「雪松湖西店:9493216」库存同步-结束......');

        // --------------------- 雪松海棠店:9493164 ---------------------
        $this->info('门店「雪松海棠店:9493164」库存同步-开始......');
        Log::info('门店「雪松海棠店:9493164」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493164' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693598', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493164';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693598';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松海棠店:9493164」库存同步-结束......');
        Log::info('门店「雪松海棠店:9493164」库存同步-结束......');

        // --------------------- 雪松旗舰店:9492506 ---------------------
        $this->info('门店「雪松旗舰店:9492506」库存同步-开始......');
        Log::info('门店「雪松旗舰店:9492506」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492506' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693599', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492506';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693599';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松旗舰店:9492506」库存同步-结束......');
        Log::info('门店「雪松旗舰店:9492506」库存同步-结束......');

        // --------------------- 雪松阳光店:9493165 ---------------------
        $this->info('门店「雪松阳光店:9493165」库存同步-开始......');
        Log::info('门店「雪松阳光店:9493165」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493165' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693600', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493165';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693600';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松阳光店:9493165」库存同步-结束......');
        Log::info('门店「雪松阳光店:9493165」库存同步-结束......');

        // --------------------- 雪松爱心店:9493089 ---------------------
        $this->info('门店「雪松爱心店:9493089」库存同步-开始......');
        Log::info('门店「雪松爱心店:9493089」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493089' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693601', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493089';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693601';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松爱心店:9493089」库存同步-结束......');
        Log::info('门店「雪松爱心店:9493089」库存同步-结束......');

        // --------------------- 雪松春天店:9493167 ---------------------
        $this->info('门店「雪松春天店:9493167」库存同步-开始......');
        Log::info('门店「雪松春天店:9493167」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493167' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693602', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493167';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693602';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松春天店:9493167」库存同步-结束......');
        Log::info('门店「雪松春天店:9493167」库存同步-结束......');

        // --------------------- 雪松河畔店:9492507 ---------------------
        $this->info('门店「雪松河畔店:9492507」库存同步-开始......');
        Log::info('门店「雪松河畔店:9492507」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492507' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693603', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492507';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693603';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松河畔店:9492507」库存同步-结束......');
        Log::info('门店「雪松河畔店:9492507」库存同步-结束......');

        // --------------------- 雪松健康店:9492509 ---------------------
        $this->info('门店「雪松健康店:9492509」库存同步-开始......');
        Log::info('门店「雪松健康店:9492509」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492509' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693604', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492509';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693604';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松健康店:9492509」库存同步-结束......');
        Log::info('门店「雪松健康店:9492509」库存同步-结束......');

        // --------------------- 雪松溪畔店:9493168 ---------------------
        $this->info('门店「雪松溪畔店:9493168」库存同步-开始......');
        Log::info('门店「雪松溪畔店:9493168」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493168' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693605', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493168';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693605';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松溪畔店:9493168」库存同步-结束......');
        Log::info('门店「雪松溪畔店:9493168」库存同步-结束......');

        // --------------------- 雪松兴隆店:9493172 ---------------------
        $this->info('门店「雪松兴隆店:9493172」库存同步-开始......');
        Log::info('门店「雪松兴隆店:9493172」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493172' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693606', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9493172';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693606';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松兴隆店:9493172」库存同步-结束......');
        Log::info('门店「雪松兴隆店:9493172」库存同步-结束......');

        // --------------------- 雪松碧桂园:9492664 ---------------------
        $this->info('门店「雪松碧桂园:9492664」库存同步-开始......');
        Log::info('门店「雪松碧桂园:9492664」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492664' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693607', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492664';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693607';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松碧桂园:9492664」库存同步-结束......');
        Log::info('门店「雪松碧桂园:9492664」库存同步-结束......');

        // --------------------- 雪松迎春店:9492666 ---------------------
        $this->info('门店「雪松迎春店:9492666」库存同步-开始......');
        Log::info('门店「雪松迎春店:9492666」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492666' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693609', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492666';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693609';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松迎春店:9492666」库存同步-结束......');
        Log::info('门店「雪松迎春店:9492666」库存同步-结束......');

        // --------------------- 雪松桂花店:9492670 ---------------------
        $this->info('门店「雪松桂花店:9492670」库存同步-开始......');
        Log::info('门店「雪松桂花店:9492670」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492670' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693610', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492670';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693610';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松桂花店:9492670」库存同步-结束......');
        Log::info('门店「雪松桂花店:9492670」库存同步-结束......');

        // --------------------- 雪松丁香店:9492671 ---------------------
        $this->info('门店「雪松丁香店:9492671」库存同步-开始......');
        Log::info('门店「雪松丁香店:9492671」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492671' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('32267693611', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '9492671';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);

                $ele_params['shop_id'] = '32267693611';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松丁香店:9492671」库存同步-结束......');
        Log::info('门店「雪松丁香店:9492671」库存同步-结束......');

        // --------------------- 雪松陈相店:9492665 ---------------------
        // $this->info('门店「雪松陈相店:9492665」库存同步-开始......');
        // Log::info('门店「雪松陈相店:9492665」库存同步-开始......');
        // $data = DB::connection('xuesong')
        //     ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492671' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        // if (!empty($data)) {
        //     $data = array_chunk($data, 100);
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
        //         $params['app_poi_code'] = '9492665';
        //         $params['medicine_data'] = json_encode($stock_data);
        //         $minkang->medicineStock($params);
        //
        //         $ele_params['shop_id'] = '32267693608';
        //         $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
        //         $ele->skuStockUpdate($ele_params);
        //     }
        // }
        // $this->info('门店「雪松陈相店:9492665」库存同步-结束......');
        // Log::info('门店「雪松陈相店:9492665」库存同步-结束......');


        $meiquan = app("meiquan");
        // --------------------- 雪松大药房（幸福店）:15440082 ---------------------
        $this->info('门店「雪松大药房（幸福店）:15440082」库存同步-开始......');
        Log::info('门店「雪松大药房（幸福店）:15440082」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15440082' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('1112301454', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '15440082';
                $params['medicine_data'] = json_encode($stock_data);
                $params['access_token'] = $meiquan->getShopToken('15440082');
                $meiquan->medicineStock($params);

                $ele_params['shop_id'] = '1112301454';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松大药房（幸福店）:15440082」库存同步-结束......');
        Log::info('门店「雪松大药房（幸福店）:15440082」库存同步-结束......');


        // --------------------- 雪松大药房（枫杨路店）:15437138 ---------------------
        $this->info('门店「雪松大药房（枫杨路店）:15437138」库存同步-开始......');
        Log::info('门店「雪松大药房（枫杨路店）:15437138」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15437138' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('1112859955', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '15437138';
                $params['medicine_data'] = json_encode($stock_data);
                $params['access_token'] = $meiquan->getShopToken('15437138');
                $meiquan->medicineStock($params);

                $ele_params['shop_id'] = '1112859955';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松大药房（枫杨路店）:15437138」库存同步-结束......');
        Log::info('门店「雪松大药房（枫杨路店）:15437138」库存同步-结束......');


        // --------------------- 雪松大药房（奥园店）:15473187 ---------------------
        $this->info('门店「雪松大药房（奥园店）:15473187」库存同步-开始......');
        Log::info('门店「雪松大药房（奥园店）:15473187」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15473187' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('507348833', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '15473187';
                $params['medicine_data'] = json_encode($stock_data);
                $params['access_token'] = $meiquan->getShopToken('15473187');
                $meiquan->medicineStock($params);

                $ele_params['shop_id'] = '507348833';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松大药房（奥园店）:15473187」库存同步-结束......');
        Log::info('门店「雪松大药房（奥园店）:15473187」库存同步-结束......');


        // --------------------- 雪松大药房（恒大名都店）:15473753 ---------------------
        $this->info('门店「雪松大药房（恒大名都店）:15473753」库存同步-开始......');
        Log::info('门店「雪松大药房（恒大名都店）:15473753」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15473753' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            // 获取饿了么商品信息
            $upcs = [];
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList('507353649', $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        $upcs[] = $v['upc'];
                    }
                } else {
                    break;
                }
            }
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $stock_data = [];
                $stock_data_ele = [];
                $upc_data = [];
                foreach ($items as $item) {
                    if (in_array($item->upc, $upc_data)) {
                        continue;
                    }
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                    if (in_array($item->upc, $upcs)) {
                        $stock_data_ele[] = $item->upc . ':' . (int) $item->stock;
                    }
                    $upc_data[] = $item->upc;
                }

                $params['app_poi_code'] = '15473753';
                $params['medicine_data'] = json_encode($stock_data);
                $params['access_token'] = $meiquan->getShopToken('15473753');
                $meiquan->medicineStock($params);

                $ele_params['shop_id'] = '507353649';
                $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                $ele_res = $ele->skuStockUpdate($ele_params);
                Log::info("雪松饿了么-结果", [$ele_res]);
            }
        }
        $this->info('门店「雪松大药房（恒大名都店）:15473753」库存同步-结束......');
        Log::info('门店「雪松大药房（恒大名都店）:15473753」库存同步-结束......');
    }
}
