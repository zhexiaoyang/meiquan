<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCodeXueSong extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-code-xuesong';

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
        $this->info('------------雪松绑定编码------------');
        $minkang = app("minkang");

        // --------------------- 雪松青年桥店:9493159 ---------------------
        $this->info('门店「雪松青年桥店:9493159」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493159' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493159';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松青年桥店:9493159」同步-结束......');

        // --------------------- 雪松站前店:9493161 ---------------------
        $this->info('门店「雪松站前店:9493161」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493161' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493161';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松站前店:9493161」同步-结束......');

        // --------------------- 雪松金山店:9493163 ---------------------
        $this->info('门店「雪松金山店:9493163」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493163' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493163';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松金山店:9493163」同步-结束......');

        // --------------------- 雪松湖西店:9493216 ---------------------
        $this->info('门店「雪松湖西店:9493216」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493216' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493216';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松湖西店:9493216」同步-结束......');

        // --------------------- 雪松海棠店:9493164 ---------------------
        $this->info('门店「雪松海棠店:9493164」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493164' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493164';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松海棠店:9493164」同步-结束......');

        // --------------------- 雪松旗舰店:9492506 ---------------------
        $this->info('门店「雪松旗舰店:9492506」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492506' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492506';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松旗舰店:9492506」同步-结束......');

        // --------------------- 雪松阳光店:9493165 ---------------------
        $this->info('门店「雪松阳光店:9493165」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493165' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493165';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松阳光店:9493165」同步-结束......');

        // --------------------- 雪松爱心店:9493089 ---------------------
        $this->info('门店「雪松爱心店:9493089」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493089' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493089';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松爱心店:9493089」同步-结束......');

        // --------------------- 雪松春天店:9493167 ---------------------
        $this->info('门店「雪松春天店:9493167」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493167' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493167';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松春天店:9493167」同步-结束......');

        // --------------------- 雪松河畔店:9492507 ---------------------
        $this->info('门店「雪松河畔店:9492507」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492507' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492507';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松河畔店:9492507」同步-结束......');

        // --------------------- 雪松健康店:9492509 ---------------------
        $this->info('门店「雪松健康店:9492509」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492509' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492509';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松健康店:9492509」同步-结束......');

        // --------------------- 雪松溪畔店:9493168 ---------------------
        $this->info('门店「雪松溪畔店:9493168」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493168' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493168';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松溪畔店:9493168」同步-结束......');

        // --------------------- 雪松兴隆店:9493172 ---------------------
        $this->info('门店「雪松兴隆店:9493172」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9493172' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9493172';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松兴隆店:9493172」同步-结束......');

        // --------------------- 雪松碧桂园:9492664 ---------------------
        $this->info('门店「雪松碧桂园:9492664」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492664' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492664';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松碧桂园:9492664」同步-结束......');

        // --------------------- 雪松迎春店:9492666 ---------------------
        $this->info('门店「雪松迎春店:9492666」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492666' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492666';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松迎春店:9492666」同步-结束......');

        // --------------------- 雪松桂花店:9492670 ---------------------
        $this->info('门店「雪松桂花店:9492670」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492670' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492670';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松桂花店:9492670」同步-结束......');

        // --------------------- 雪松丁香店:9492671 ---------------------
        $this->info('门店「雪松丁香店:9492671」同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [meituan] = N'9492671' AND [upc] <> '' AND [upc] IS NOT NULL");
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
                $params['app_poi_code'] = '9492671';
                $params['medicine_data'] = json_encode($code_data);
                $minkang->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松丁香店:9492671」同步-结束......');

    }
}
