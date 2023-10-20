<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;

class SyncStockYongQinTangPrice extends Command
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
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-price-yongqintang';

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
        $price_data_res = $this->getPrice(2 , date("Y-m-d"));
        // $price_data_res = $this->getPrice(2 , date("Y-m-d", strtotime('-2 day')));
        if (empty($price_data_res)) {
            \Log::info("永沁堂更新价格-更改价格数量为0");
            return ;
        }
        $this->info("永沁堂更新价格-总数：" . count($price_data_res));
        \Log::info("永沁堂更新价格-总数：" . count($price_data_res));
        $meiquan = app('meiquan');
        $minkang = app('minkang');
        foreach ($this->shops as $shop) {
            $data = $price_data_res;
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $code_data = [];
                $price_data = [];
                $upc_data = [];
                foreach ($items as $item) {
                    $pid = $item['pcode'];
                    $price = $item['preprice1'];
                    if ($price <= 0) {
                        continue;
                    }
                    $product = $this->getProduct($pid);
                    if (empty($product['barcode'])) {
                        continue;
                    }
                    $code = $product['code'];
                    $name = $product['name'];
                    $upc = $product['barcode'];
                    if (in_array($upc, $upc_data)) {
                        continue;
                    }
                    $upc_data[] = $upc;
                    $cost = $product['recbuyprice'];

                    \Log::info("永沁堂更新价格|{$shop['name']}|商品：{$name}|商品：{$code}|条码：{$upc}|价格：{$price}|成本：{$cost}");
                    Medicine::where('shop_id', $shop['mid'])->where('upc', $upc)->update(['price' => $price]);
                    // Medicine::where('shop_id', $shop['mid'])->where('upc', $upc)->update(['store_id' => $code, 'price' => $price, 'guidance_price' => $cost]);

                    // 商家商品ID
                    $store_id = $upc;
                    // 组合数组
                    $code_data[] = [
                        'upc' => $upc,
                        'app_medicine_code_new' => $store_id,
                    ];
                    $price_data[] = [
                        'app_medicine_code' => $store_id,
                        'price' => (float) $price,
                    ];
                }
                $this->info("{$shop['name']}-第一批总数：" . count($upc_data));

                // 绑定编码
                $params_code['app_poi_code'] = $shop['mtid'];
                $params_code['medicine_data'] = json_encode($code_data);
                $params_price['app_poi_code'] = $shop['mtid'];
                $params_price['medicine_data'] = json_encode($price_data);
                $this->info(json_encode($params_price));

                if ($shop['bind_type'] === 4) {
                    $minkang->medicineCodeUpdate($params_code);
                    $minkang->medicinePrice($params_price);
                } else {
                    $params_code['access_token'] = $meiquan->getShopToken($shop['mtid']);
                    $params_price['access_token'] = $meiquan->getShopToken($shop['mtid']);
                    $meiquan->medicineCodeUpdate($params_code);
                    $meiquan->medicinePrice($params_price);
                }
            }
            // break;
        }
    }

    /**
     * 获取商品价格
     */
    public function getPrice($yid, $date)
    {
        $data = [
            'appid' => $this->app_id,
            'accountName' => $this->account,
            'timeStamp' => (string) (time() * 1000),
            'yid' => (string) $yid,
            'page' => '1',
            'rows' => '10000',
            'modifyDate' => $date,
            // 'modifyDate' => '2023-09-05',
        ];
        $data['sign'] = $this->encryptData(json_encode($data, JSON_UNESCAPED_UNICODE), $this->app_key);
        $res = $this->doPost($this->url . 'getprice.api', $data);
        $res_data = json_decode($res, true);
        return $res_data['data']['rows'] ?? [];
    }

    /**
     * 获取商品详细信息
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
