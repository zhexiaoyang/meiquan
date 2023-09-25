<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
            'name' => '昌平大药房（玉屏路店）'
        ],
        [
            'yid' => 12,
            'mtid' => '14264178',
            'bind' => 'minkang',
            'bind_type' => 4,
            'name' => '昌平大药房（渝西大道店）'
        ],
        [
            'yid' => 13,
            'mtid' => '14281885',
            'bind' => 'minkang',
            'bind_type' => 4,
            'name' => '昌平大药房（萱花路店）'
        ],
        [
            'yid' => 21,
            'mtid' => '18012051',
            'bind' => 'shangou',
            'bind_type' => 31,
            'name' => '永沁堂药房（泸州街店）'
        ],
        [
            'yid' => 3,
            'mtid' => '14265475',
            'bind' => 'minkang',
            'bind_type' => 4,
            'name' => '永沁堂大药房（人民南路店）'
        ],
        [
            'yid' => 4,
            'mtid' => '14282217',
            'bind' => 'minkang',
            'bind_type' => 4,
            'name' => '永沁堂大药房（汇龙东二路店）'
        ],
        [
            'yid' => 6,
            'mtid' => '14281884',
            'bind' => 'minkang',
            'bind_type' => 4,
            'name' => '永沁堂大药房（凤栖店）'
        ],
        [
            'yid' => 5,
            'mtid' => '18859007',
            'bind' => 'shangou',
            'bind_type' => 31,
            'name' => '永沁堂大药房（二门市店）'
        ],
        [
            'yid' => 7,
            'mtid' => '18859008',
            'bind' => 'shangou',
            'bind_type' => 31,
            'name' => '永沁堂大药房（五门市店）'
        ],
        [
            'yid' => 11,
            'mtid' => '18857417',
            'bind' => 'shangou',
            'bind_type' => 31,
            'name' => '永沁堂大药房（万来店）'
        ],
        [
            'yid' => 18,
            'mtid' => '18854087',
            'bind' => 'shangou',
            'bind_type' => 31,
            'name' => '永沁堂大药房(平康店)'
        ],
        [
            'yid' => 24,
            'mtid' => '18832866',
            'bind' => 'shangou',
            'bind_type' => 31,
            'name' => '昌平大药房（万向店）'
        ],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-yongqintang';

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
        $meiquan = app('meiquan');
        $minkang = app('minkang');
        foreach ($this->shops as $shop) {
            // $stock_data = $this->modifyStock($shop['yid'] , date("Y-m-d", time() - 86400));
            $stock_data = $this->modifyStock($shop['yid'] , date("Y-m-d"));
            if (empty($stock_data) || !isset($stock_data['rows'])) {
                \Log::info("{$shop['name']}-更改库存数量为0");
                continue;
            }
            $data = $stock_data['rows'];
            $this->info("{$shop['name']}-总数：" . count($data));
            $data = array_chunk($data, 100);
            foreach ($data as $items) {
                $code_data = [];
                $stock_data = [];
                $upc_data = [];
                foreach ($items as $item) {
                    $pid = $item['pid'];
                    $name = $item['pname'];
                    $quantity = $item['quantity'];
                    $product = $this->getProduct($name);
                    if (empty($product['barcode'])) {
                        continue;
                    }
                    $upc = $product['barcode'];
                    if (in_array($upc, $upc_data)) {
                        continue;
                    }
                    $upc_data[] = $upc;
                    $cost = $product['recbuyprice'];
                    // $price = $this->getPrice($pid);
                    // if (empty($price[''])) {
                    //     continue;
                    // }
                    // \Log::info("{$shop['name']}|{$upc}|{$quantity}|{$cost}|{$name}|{$shop['name']}");
                    // \Log::info('stock', $item);
                    // \Log::info('product', $product);
                    // \Log::info('price', $price);

                    // 商家商品ID
                    $store_id = $upc;
                    // 组合数组
                    $code_data[] = [
                        'upc' => $upc,
                        'app_medicine_code_new' => $store_id,
                    ];
                    $stock_data[] = [
                        'app_medicine_code' => $store_id,
                        'stock' => (int) $quantity,
                    ];
                }
                $this->info("{$shop['name']}-第一批总数：" . count($upc_data));

                // 绑定编码
                $params_code['app_poi_code'] = $shop['mtid'];
                $params_code['medicine_data'] = json_encode($code_data);
                $params_stock['app_poi_code'] = $shop['mtid'];
                $params_stock['medicine_data'] = json_encode($stock_data);

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
