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

        // --------------------- 雪松青年桥店:9493159 ---------------------
        $this->info('门店「雪松青年桥店:9493159」库存同步-开始......');
        Log::info('门店「雪松青年桥店:9493159」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493159' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493159';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松青年桥店:9493159」库存同步-结束......');
        Log::info('门店「雪松青年桥店:9493159」库存同步-结束......');

        // --------------------- 雪松站前店:9493161 ---------------------
        $this->info('门店「雪松站前店:9493161」库存同步-开始......');
        Log::info('门店「雪松站前店:9493161」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493161' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493161';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松站前店:9493161」库存同步-结束......');
        Log::info('门店「雪松站前店:9493161」库存同步-结束......');

        // --------------------- 雪松金山店:9493163 ---------------------
        $this->info('门店「雪松金山店:9493163」库存同步-开始......');
        Log::info('门店「雪松金山店:9493163」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493163' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493163';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松金山店:9493163」库存同步-结束......');
        Log::info('门店「雪松金山店:9493163」库存同步-结束......');

        // --------------------- 雪松湖西店:9493216 ---------------------
        $this->info('门店「雪松湖西店:9493216」库存同步-开始......');
        Log::info('门店「雪松湖西店:9493216」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493216' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493216';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松湖西店:9493216」库存同步-结束......');
        Log::info('门店「雪松湖西店:9493216」库存同步-结束......');

        // --------------------- 雪松海棠店:9493164 ---------------------
        $this->info('门店「雪松海棠店:9493164」库存同步-开始......');
        Log::info('门店「雪松海棠店:9493164」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493164' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493164';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松海棠店:9493164」库存同步-结束......');
        Log::info('门店「雪松海棠店:9493164」库存同步-结束......');

        // --------------------- 雪松旗舰店:9492506 ---------------------
        $this->info('门店「雪松旗舰店:9492506」库存同步-开始......');
        Log::info('门店「雪松旗舰店:9492506」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492506' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492506';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松旗舰店:9492506」库存同步-结束......');
        Log::info('门店「雪松旗舰店:9492506」库存同步-结束......');

        // --------------------- 雪松阳光店:9493165 ---------------------
        $this->info('门店「雪松阳光店:9493165」库存同步-开始......');
        Log::info('门店「雪松阳光店:9493165」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493165' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493165';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松阳光店:9493165」库存同步-结束......');
        Log::info('门店「雪松阳光店:9493165」库存同步-结束......');

        // --------------------- 雪松爱心店:9493089 ---------------------
        $this->info('门店「雪松爱心店:9493089」库存同步-开始......');
        Log::info('门店「雪松爱心店:9493089」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493089' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493089';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松爱心店:9493089」库存同步-结束......');
        Log::info('门店「雪松爱心店:9493089」库存同步-结束......');

        // --------------------- 雪松春天店:9493167 ---------------------
        $this->info('门店「雪松春天店:9493167」库存同步-开始......');
        Log::info('门店「雪松春天店:9493167」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493167' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493167';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松春天店:9493167」库存同步-结束......');
        Log::info('门店「雪松春天店:9493167」库存同步-结束......');

        // --------------------- 雪松河畔店:9492507 ---------------------
        $this->info('门店「雪松河畔店:9492507」库存同步-开始......');
        Log::info('门店「雪松河畔店:9492507」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492507' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492507';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松河畔店:9492507」库存同步-结束......');
        Log::info('门店「雪松河畔店:9492507」库存同步-结束......');

        // --------------------- 雪松健康店:9492509 ---------------------
        $this->info('门店「雪松健康店:9492509」库存同步-开始......');
        Log::info('门店「雪松健康店:9492509」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492509' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492509';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松健康店:9492509」库存同步-结束......');
        Log::info('门店「雪松健康店:9492509」库存同步-结束......');

        // --------------------- 雪松溪畔店:9493168 ---------------------
        $this->info('门店「雪松溪畔店:9493168」库存同步-开始......');
        Log::info('门店「雪松溪畔店:9493168」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493168' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493168';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松溪畔店:9493168」库存同步-结束......');
        Log::info('门店「雪松溪畔店:9493168」库存同步-结束......');

        // --------------------- 雪松兴隆店:9493172 ---------------------
        $this->info('门店「雪松兴隆店:9493172」库存同步-开始......');
        Log::info('门店「雪松兴隆店:9493172」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493172' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9493172';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松兴隆店:9493172」库存同步-结束......');
        Log::info('门店「雪松兴隆店:9493172」库存同步-结束......');

        // --------------------- 雪松碧桂园:9492664 ---------------------
        $this->info('门店「雪松碧桂园:9492664」库存同步-开始......');
        Log::info('门店「雪松碧桂园:9492664」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492664' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492664';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松碧桂园:9492664」库存同步-结束......');
        Log::info('门店「雪松碧桂园:9492664」库存同步-结束......');

        // --------------------- 雪松迎春店:9492666 ---------------------
        $this->info('门店「雪松迎春店:9492666」库存同步-开始......');
        Log::info('门店「雪松迎春店:9492666」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492666' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492666';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松迎春店:9492666」库存同步-结束......');
        Log::info('门店「雪松迎春店:9492666」库存同步-结束......');

        // --------------------- 雪松桂花店:9492670 ---------------------
        $this->info('门店「雪松桂花店:9492670」库存同步-开始......');
        Log::info('门店「雪松桂花店:9492670」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492670' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492670';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松桂花店:9492670」库存同步-结束......');
        Log::info('门店「雪松桂花店:9492670」库存同步-结束......');

        // --------------------- 雪松丁香店:9492671 ---------------------
        $this->info('门店「雪松丁香店:9492671」库存同步-开始......');
        Log::info('门店「雪松丁香店:9492671」库存同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492671' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => $item->id,
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '9492671';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「雪松丁香店:9492671」库存同步-结束......');
        Log::info('门店「雪松丁香店:9492671」库存同步-结束......');
    }
}
