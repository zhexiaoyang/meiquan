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
        $this->info('门店「雪松青年桥店:9493159」编码绑定同步-开始......');
        Log::info('门店「雪松青年桥店:9493159」编码绑定同步-开始......');
        // SELECT TOP 1000 * FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493159'
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493159' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            // Log::info("data", [$data]);
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
        $this->info('门店「雪松青年桥店:9493159」编码绑定同步-结束......');
        Log::info('门店「雪松青年桥店:9493159」编码绑定同步-结束......');

        // --------------------- 雪松站前店:9493161 ---------------------
        $this->info('门店「雪松站前店:9493161」编码绑定同步-开始......');
        Log::info('门店「雪松站前店:9493161」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493161' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松站前店:9493161」编码绑定同步-结束......');
        Log::info('门店「雪松站前店:9493161」编码绑定同步-结束......');

        // --------------------- 雪松金山店:9493163 ---------------------
        $this->info('门店「雪松金山店:9493163」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493163' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松金山店:9493163」编码绑定同步-结束......');

        // --------------------- 雪松湖西店:9493216 ---------------------
        $this->info('门店「雪松湖西店:9493216」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493216' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松湖西店:9493216」编码绑定同步-结束......');

        // --------------------- 雪松海棠店:9493164 ---------------------
        $this->info('门店「雪松海棠店:9493164」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493164' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松海棠店:9493164」编码绑定同步-结束......');

        // --------------------- 雪松旗舰店:9492506 ---------------------
        $this->info('门店「雪松旗舰店:9492506」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492506' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松旗舰店:9492506」编码绑定同步-结束......');

        // --------------------- 雪松阳光店:9493165 ---------------------
        $this->info('门店「雪松阳光店:9493165」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493165' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松阳光店:9493165」编码绑定同步-结束......');

        // --------------------- 雪松爱心店:9493089 ---------------------
        $this->info('门店「雪松爱心店:9493089」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493089' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松爱心店:9493089」编码绑定同步-结束......');

        // --------------------- 雪松春天店:9493167 ---------------------
        $this->info('门店「雪松春天店:9493167」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493167' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松春天店:9493167」编码绑定同步-结束......');

        // --------------------- 雪松河畔店:9492507 ---------------------
        $this->info('门店「雪松河畔店:9492507」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492507' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松河畔店:9492507」编码绑定同步-结束......');

        // --------------------- 雪松健康店:9492509 ---------------------
        $this->info('门店「雪松健康店:9492509」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492509' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松健康店:9492509」编码绑定同步-结束......');

        // --------------------- 雪松溪畔店:9493168 ---------------------
        $this->info('门店「雪松溪畔店:9493168」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493168' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松溪畔店:9493168」编码绑定同步-结束......');

        // --------------------- 雪松兴隆店:9493172 ---------------------
        $this->info('门店「雪松兴隆店:9493172」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9493172' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松兴隆店:9493172」编码绑定同步-结束......');

        // --------------------- 雪松碧桂园:9492664 ---------------------
        $this->info('门店「雪松碧桂园:9492664」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492664' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松碧桂园:9492664」编码绑定同步-结束......');

        // --------------------- 雪松迎春店:9492666 ---------------------
        $this->info('门店「雪松迎春店:9492666」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492666' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松迎春店:9492666」编码绑定同步-结束......');

        // --------------------- 雪松桂花店:9492670 ---------------------
        $this->info('门店「雪松桂花店:9492670」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492670' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松桂花店:9492670」编码绑定同步-结束......');

        // --------------------- 雪松丁香店:9492671 ---------------------
        $this->info('门店「雪松丁香店:9492671」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'9492671' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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
        $this->info('门店「雪松丁香店:9492671」编码绑定同步-结束......');

        $meiquan = app("meiquan");
        // --------------------- 雪松大药房（幸福店）:15440082 ---------------------
        $this->info('门店「雪松大药房（幸福店）:15440082」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15440082' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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

                // 绑定商品编码
                $params['app_poi_code'] = '15440082';
                $params['medicine_data'] = json_encode($code_data);
                $params['access_token'] = $meiquan->getShopToken('15440082');
                $meiquan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松大药房（幸福店）:15440082」编码绑定同步-结束......');

        // ---------------------雪松大药房（枫杨路店）:15437138 ---------------------
        $this->info('门店「雪松大药房（枫杨路店）:15437138」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15437138' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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

                // 绑定商品编码
                $params['app_poi_code'] = '15437138';
                $params['medicine_data'] = json_encode($code_data);
                $params['access_token'] = $meiquan->getShopToken('15437138');
                $meiquan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松大药房（枫杨路店）:15437138」编码绑定同步-结束......');

        // ---------------------雪松大药房（奥园店）:15473187 ---------------------
        $this->info('门店「雪松大药房（奥园店）:15473187」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15473187' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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

                // 绑定商品编码
                $params['app_poi_code'] = '15473187';
                $params['medicine_data'] = json_encode($code_data);
                $params['access_token'] = $meiquan->getShopToken('15473187');
                $meiquan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松大药房（奥园店）:15473187」编码绑定同步-结束......');

        // ---------------------雪松大药房（恒大名都店）:15473753 ---------------------
        $this->info('门店「雪松大药房（恒大名都店）:15473753」编码绑定同步-开始......');
        $data = DB::connection('xuesong')
            ->select("SELECT bianhao as id, tiaoma as upc FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'15473753' AND [tiaoma] <> '' AND [tiaoma] IS NOT NULL");
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

                // 绑定商品编码
                $params['app_poi_code'] = '15473753';
                $params['medicine_data'] = json_encode($code_data);
                $params['access_token'] = $meiquan->getShopToken('15473753');
                $meiquan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「雪松大药房（恒大名都店）:15473753」编码绑定同步-结束......');

    }
}
