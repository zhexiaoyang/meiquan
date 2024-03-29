<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SyncStockYongQinTang extends Command
{

    public $url = 'http://cqbe.seaflysoft.com/';
    public $app_id = 'sFmW6idF';
    public $app_key = 'pye5cYk6';
    public $account = '重庆永沁堂大药房';

    public $shops = [
        [
            'yid' => 10,
            'mtid' => '14239678',
            'bind' => 'minkang',
            'bind_type' => 4,
            'mid' => 5791,
            'name' => '昌平大药房（玉屏路店）'
        ],
        [
            'yid' => 12,
            'mtid' => '14264178',
            'bind' => 'minkang',
            'bind_type' => 4,
            'mid' => 5792,
            'name' => '昌平大药房（渝西大道店）'
        ],
        [
            'yid' => 13,
            'mtid' => '14281885',
            'bind' => 'minkang',
            'bind_type' => 4,
            'mid' => 5793,
            'name' => '昌平大药房（萱花路店）'
        ],
        [
            'yid' => 21,
            'mtid' => '18012051',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 6607,
            'name' => '永沁堂大药房（泸州街店）'
        ],
        [
            'yid' => 3,
            'mtid' => '14265475',
            'bind' => 'minkang',
            'bind_type' => 4,
            'mid' => 5224,
            'name' => '永沁堂大药房（人民南路店）'
        ],
        [
            'yid' => 4,
            'mtid' => '14282217',
            'bind' => 'minkang',
            'bind_type' => 4,
            'mid' => 5240,
            'name' => '永沁堂大药房（汇龙东二路店）'
        ],
        [
            'yid' => 6,
            'mtid' => '14281884',
            'bind' => 'minkang',
            'bind_type' => 4,
            'mid' => 5237,
            'name' => '永沁堂大药房（凤栖店）'
        ],
        [
            'yid' => 5,
            'mtid' => '18859007',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 5790,
            'name' => '永沁堂大药房（二门市店）'
        ],
        [
            'yid' => 7,
            'mtid' => '18859008',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 5238,
            'name' => '永沁堂大药房（五门市店）'
        ],
        [
            'yid' => 11,
            'mtid' => '18857417',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 6818,
            'name' => '永沁堂大药房（万来店）'
        ],
        [
            'yid' => 18,
            'mtid' => '18854087',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 6819,
            'name' => '永沁堂大药房(平康店)'
        ],
        [
            'yid' => 24,
            'mtid' => '18832866',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 6820,
            'name' => '昌平大药房（万向店）'
        ],
        [
            'yid' => 25,
            'mtid' => '19232483',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 7029,
            'name' => '昌平大药房（自源店）'
        ],
        [
            'yid' => 8,
            'mtid' => '18859000',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 5239,
            'name' => '永沁堂大药房（六门市店）'
        ],
        [
            'yid' => 25,
            'mtid' => '19416431',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 7067,
            'name' => '永沁堂大药房(万城店)'
        ],
        [
            'yid' => 15,
            'mtid' => '19684153',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 7159,
            'name' => '汇景康大药房（总店）'
        ],
        [
            'yid' => 20,
            'mtid' => '19686151',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 7160,
            'name' => '汇景康大药房（万福店） 	'
        ],
        [
            'yid' => 17,
            'mtid' => '19686242',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 7161,
            'name' => '汇景康大药房（万顺店）'
        ],
        [
            'yid' => 23,
            'mtid' => '19686267',
            'bind' => 'shangou',
            'bind_type' => 31,
            'mid' => 7162,
            'name' => '汇景康大药房（万众店）'
        ],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-yongqintang {num}';

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
        $redis_key = "upcs:yongqintang";
        $meiquan = app('meiquan');
        $minkang = app('minkang');
        $num = $this->argument('num');
        if (is_numeric($num)) {
            $shops = [$this->shops[$num]];
        } else {
            $shops = $this->shops;
        }
        foreach ($shops as $shop) {
            // $stock_data = $this->modifyStock($shop['yid'] , date("Y-m-d", time() - 86400));
            $stock_data = $this->modifyStock($shop['yid'] , date("Y-m-d"));
            if (empty($stock_data) || !isset($stock_data['rows'])) {
                \Log::info("{$shop['name']}-更改库存数量为0");
                continue;
            }
            $data = $stock_data['rows'];
            $this->info("{$shop['name']}-总数：" . count($data));
            $data = array_chunk($data, 100);
            foreach ($data as $key => $items) {
                $code_data = [];
                $stock_data = [];
                foreach ($items as $item) {
                    $pid = $item['pid'];
                    $quantity = (int) $item['quantity'];
                    if (!$upc = Redis::hget($redis_key, $pid)) {
                        continue;
                    }
                    // 商家商品ID
                    $store_id = $upc;
                    // 组合数组
                    $code_data[$upc] = [
                        'upc' => $upc,
                        'app_medicine_code_new' => $store_id,
                    ];
                    $stock_data[$upc] = [
                        'app_medicine_code' => $store_id,
                        'stock' => $quantity,
                    ];
                    \Log::info("永沁堂更新价格|{$shop['name']}|条码：{$upc}|库存：{$quantity}");
                    Medicine::where('shop_id', $shop['mid'])->where('upc', $upc)->update(['stock' => $quantity]);
                }
                $this->info("{$shop['name']}-第{$key}批总数：" . count($code_data));

                // 绑定编码
                $params_code['app_poi_code'] = $shop['mtid'];
                $params_code['medicine_data'] = json_encode(array_values($code_data));
                $params_stock['app_poi_code'] = $shop['mtid'];
                $params_stock['medicine_data'] = json_encode(array_values($stock_data));

                if ($shop['bind_type'] === 4) {
                    $minkang->medicineCodeUpdate($params_code);
                    $minkang->medicineStock($params_stock);
                } else {
                    $params_code['access_token'] = $meiquan->getShopToken($shop['mtid']);
                    $params_stock['access_token'] = $meiquan->getShopToken($shop['mtid']);
                    $meiquan->medicineCodeUpdate($params_code);
                    $meiquan->medicineStock($params_stock);
                }
            }
            // break;
        }
    }

    /**
     * 获取商品价格
     * @param $name
     * @return array|mixed
     * @author zhangzhen
     * @data 2023/7/20 9:20 下午
     */
    public function getPrice($pid)
    {
        $data = [
            'appid' => $this->app_id,
            'accountName' => $this->account,
            'timeStamp' => (string) (time() * 1000),
            'pids' => (string) $pid,
        ];
        $data['sign'] = $this->encryptData(json_encode($data, JSON_UNESCAPED_UNICODE), $this->app_key);
        $res = $this->doPost($this->url . 'getprice.api', $data);
        $res_data = json_decode($res, true);
        return $res_data['data']['rows'][0] ?? [];
    }

    /**
     * 获取商品详细信息
     * @param $name
     * @return array|mixed
     * @author zhangzhen
     * @data 2023/7/20 9:19 下午
     */
    public function getProduct($name)
    {
        $data = [
            'appid' => $this->app_id,
            'accountName' => $this->account,
            'timeStamp' => (string) (time() * 1000),
            'dim' => $name,
        ];
        $data['sign'] = $this->encryptData(json_encode($data, JSON_UNESCAPED_UNICODE), $this->app_key);
        $res = $this->doPost($this->url . 'getproducts.api', $data);
        $res_data = json_decode($res, true);
        return $res_data['data']['rows'][0] ?? [];
    }

    /**
     * 根据日期获取修改过库存的商品
     * @param $yid
     * @param $date
     * @return array|mixed
     * @author zhangzhen
     * @data 2023/7/20 9:06 下午
     */
    public function modifyStock($yid, $date)
    {
        $result = [];
        // 获取库存信息-开始
        $data = [
            'appid' => $this->app_id,
            'accountName' => $this->account,
            'timeStamp' => (string) (time() * 1000),
            'yid' => (string) $yid,
            'page' => '1',
            'rows' => '10000',
            'modifydate' => $date,
        ];

        $data['sign'] = $this->encryptData(json_encode($data, JSON_UNESCAPED_UNICODE), $this->app_key);
        $res = $this->doPost($this->url . 'getstorehouse.api', $data);
        $res_data = json_decode($res, true);
        if (isset($res_data['data'])) {
            $res_data_data = json_decode($res_data['data'], true);
            if (!empty($res_data_data)) {
                $result = $res_data_data;
            }
        }
        // 获取库存信息-结束
        return $result;
    }


    public function encryptData($input, $key)
    {
        $ivlen = openssl_cipher_iv_length('DES-ECB');    // 获取密码iv长度
        $iv = openssl_random_pseudo_bytes($ivlen);        // 生成一个伪随机字节串
        $data = openssl_encrypt($input, 'DES-ECB', $key, $options=OPENSSL_RAW_DATA, $iv);    // 加密
        return bin2hex($data);
    }

    public static function doPost($url, $param)
    {
        $ch = curl_init();
        $header = ['Content-Type:multipart/form-data']; //设置一个你的浏览器agent的header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}
