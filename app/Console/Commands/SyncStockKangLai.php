<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncStockKangLai extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-kanglai';

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
        // 外卖平台信息
        $mid = 6729;
        $mt_id = '18482715';
        $ele_id = '1161803008';
        $meituan = app('meiquan');
        $ele = app('ele');
        // 获取饿了么信息，放入Redis中
        $redis_key = "ele_upcs:{$ele_id}";
        if (!Redis::exists($redis_key)) {
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList($ele_id, $i, 100);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    foreach ($res['body']['data']['list'] as $v) {
                        if (!empty($v['upc'])) {
                            Redis::sadd($redis_key, $v['upc']);
                        }
                    }
                } else {
                    break;
                }
            }
            Redis::expire($redis_key, 3600);
        }
        // 三方参数
        $url = 'https://api.dbazure.cn/sa3/order.ashx?t=1';
        $params = [
            'WESN' => 11031,
            'UserName' => '13620782014',
            'PassWord' => '12345678',
            // 'KeyWord' => '123',
            'timestamp' => time() * 1000,
            'perPageCount' => 5000,
            'currentPage' => 1
        ];
        $params['sign'] = md5($params['WESN'] . $params['UserName'] . $params['PassWord'] . $params['timestamp']);
        // 初始化curl
        $headers = [
            'content-type' => 'application/json'
        ];
        $http = new HttpClient($headers);
        // 循环取数据并同步
        for ($i = 1; $i < 20; $i++) {
            $params['currentPage'] = $i;
            $response = $http->request('post', $url, ['headers' => $params, 'json' => []]);
            $res = json_decode(strval($response->getBody()), true);
            $res_data = $res['data'] ?? [];
            if (!empty($res_data)) {
                $data = array_chunk($res_data, 100);
                foreach ($data as $key => $items) {
                    $code_data = [];
                    $stock_data = [];
                    $stock_data_ele = [];
                    $upc_data = [];
                    foreach ($items as $item) {
                        // 取单个数据
                        $stock = (int) $item['StockQty'];
                        $stock = $stock >= 0 ? $stock : 0;
                        $upc = $item['ICCode'];
                        $cost = $item['Costing'];
                        $price = $item['Price'];
                        // $store_id = $item['PID'];
                        $store_id = $upc;
                        if (in_array($upc, $upc_data)) {
                            // Log::info('重复：' . $upc);
                            continue;
                        }
                        $code_data[] = [
                            'upc' => $upc,
                            'app_medicine_code_new' => $store_id,
                        ];
                        $stock_data[] = [
                            'app_medicine_code' => $store_id,
                            'stock' => $stock,
                        ];
                        if (Redis::sismember($redis_key, $upc)) {
                            $stock_data_ele[] = $upc . ':' . $stock;
                        }
                        $upc_data[] = $upc;
                        // 更新中台
                        Medicine::where('shop_id', $mid)->where('upc', $upc)->update(['price' => $price, 'guidance_price' => $cost, 'stock' => $stock]);
                    }

                    $params_code['app_poi_code'] = $mt_id;
                    $params_code['medicine_data'] = json_encode($code_data);
                    $params['app_poi_code'] = $mt_id;
                    $params['medicine_data'] = json_encode($stock_data);
                    $params_code['access_token'] = $meituan->getShopToken($mt_id);
                    $params['access_token'] = $meituan->getShopToken($mt_id);
                    $coderes = $meituan->medicineCodeUpdate($params_code);
                    $mtres = $meituan->medicineStock($params);
                    Log::info("康莱大药房美团:{$mt_id}」code-请求参数", $params_code);
                    Log::info("康莱大药房美团:{$mt_id}」code-结果", [$coderes]);
                    Log::info("康莱大药房美团:{$mt_id}」同步库存-请求参数", $stock_data);
                    Log::info("康莱大药房美团:{$mt_id}」同步库存-结果", [$mtres]);
                    // Log::info("康莱大药房美团:{$mt_id}」同步库存-i:{$i}-key:{$key}-" . count($stock_data));
                    if (!empty($stock_data_ele)) {
                        $ele_params['shop_id'] = $ele_id;
                        $ele_params['upc_stocks'] = implode(';', $stock_data_ele);
                        // Log::info("康莱大药房饿了么:{$mt_id}」同步库存-i:{$i}-key:{$key}-" . count($stock_data_ele));
                        $eleres = $ele->skuStockUpdate($ele_params);
                        Log::info("康莱大药房饿了么:{$ele_id}」同步库存-请求参数", $stock_data_ele);
                        Log::info("康莱大药房饿了么:{$ele_id}」同步库存-结果", [$eleres]);
                    } else {
                        Log::info("康莱大药房饿了么:{$ele_id}」同步库存-未同步");
                        // Log::info("康莱大药房饿了么:{$mt_id}」同步库存-i:{$i}-key:{$key}-未同步");
                    }
                }
                Log::info("康莱批次------------------$i------------------");
            } else {
                Log::info("康莱批次------------------$i------------------跳出");
                break;
            }
        }
        unset($data);
        unset($response);
        unset($res);
    }
}
