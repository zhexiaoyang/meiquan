<?php

namespace App\Http\Controllers\Erp\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAccessKey;
use App\Models\ErpAccessShop;
use App\Models\ErpDepot;
use App\Models\ErpShopCategory;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * 同步库存
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/25 12:58 上午
     */
    public function stock(Request $request)
    {
        \Log::info("[ERP接口]-[同步库存]-全部参数", $request->all());
        if (!$access_key = $request->get("access_key")) {
            return $this->error("参数错误：access_key必传", 701);
        }

        if (!$signature = $request->get("signature")) {
            return $this->error("参数错误：signature必传", 701);
        }

        if (!$timestamp = $request->get("timestamp")) {
            return $this->error("参数错误：timestamp必传", 701);
        }

        if (($timestamp < time() - 300) || ($timestamp > time() + 300)) {
            return $this->error("参数错误：timestamp有误", 701);
        }

        if (!$shop_id = $request->get("shop_id")) {
            return $this->error("参数错误：shop_id不存在", 701);
        }

        if (!$data = $request->get("data")) {
            return $this->error("参数错误：data不存在", 701);
        }

        if (empty($data)) {
            return $this->error("参数错误：data不能为空", 701);
        }

        if (count($data) > 200) {
            return $this->error("参数错误：data内容不能超过200组", 701);
        }

        if (!$access = ErpAccessKey::query()->where("access_key", $access_key)->first()) {
            return $this->error("参数错误：access_key错误", 701);
        }

        if (!$access_shop = ErpAccessShop::query()->where(['shop_id' => $shop_id, 'access_id' => $access->id])->first()) {
            return $this->error("参数错误：shop_id错误", 701);
        }

        if (!$mt_shop_id = $access_shop->mt_shop_id) {
            return $this->error("系统错误", 701);
        }

        if (!$this->checkSing($request->only("access_key", "timestamp", "shop_id", "data", "signature"), $access->access_secret)) {
            return $this->error("签名错误", 703);
        }


        $medicine_data = [];

        foreach ($data as $v) {
            if (isset($v['code']) && isset($v['stock'])) {
                $tmp['app_poi_code'] = $mt_shop_id;
                $tmp['app_medicine_code'] = $v['code'];
                $tmp['stock'] = $v['stock'];
                $medicine_data[] = $tmp;
            }
        }

        if (empty($medicine_data)) {
            return $this->error("参数错误：data内容错误", 701);
        }

        $type = $access_shop->type;

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif ($type === 2) {
            $meituan = app("mrx");
        } elseif ($type === 3) {
            $meituan = app("jay");
        } elseif ($type === 4) {
            $meituan = app("minkang");
        } elseif ($type === 5) {
            $meituan = app("qinqu");
        } else {
            return $this->error("系统错误", 701);
        }

        $params['app_poi_code'] = $mt_shop_id;
        $params['medicine_data'] = json_encode($medicine_data);

        $res = $meituan->medicineStock($params);

        if ($res['data'] != 'ok') {
            return $this->error($res['error']['msg'] ?? "", 3004);
        }

        return $this->success();
    }

    /**
     * 批量更新商品编码
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/25 8:27 上午
     */
    public function codeUpdate(Request $request)
    {
        \Log::info("[ERP接口]-[更新商品编码]-全部参数", $request->all());
        if (!$access_key = $request->get("access_key")) {
            return $this->error("参数错误：access_key必传", 701);
        }

        if (!$signature = $request->get("signature")) {
            return $this->error("参数错误：signature必传", 701);
        }

        if (!$timestamp = $request->get("timestamp")) {
            return $this->error("参数错误：timestamp必传", 701);
        }

        if (($timestamp < time() - 300) || ($timestamp > time() + 300)) {
            return $this->error("参数错误：timestamp有误", 701);
        }

        if (!$shop_id = $request->get("shop_id")) {
            return $this->error("参数错误：shop_id不存在", 701);
        }

        if (!$data = $request->get("data")) {
            return $this->error("参数错误：data不存在", 701);
        }

        if (empty($data)) {
            return $this->error("参数错误：data不能为空", 701);
        }

        if (count($data) > 200) {
            return $this->error("参数错误：data内容不能超过200组", 701);
        }

        if (!$access = ErpAccessKey::query()->where("access_key", $access_key)->first()) {
            return $this->error("参数错误：access_key错误", 701);
        }

        if (!$access_shop = ErpAccessShop::query()->where(['shop_id' => $shop_id, 'access_id' => $access->id])->first()) {
            return $this->error("参数错误：shop_id错误", 701);
        }

        if (!$mt_shop_id = $access_shop->mt_shop_id) {
            return $this->error("系统错误", 701);
        }

        if (!$this->checkSing($request->only("access_key", "timestamp", "data", "shop_id", "signature"), $access->access_secret)) {
            return $this->error("签名错误", 703);
        }

        $code_data = [];

        foreach ($data as $v) {
            if (isset($v['code']) && isset($v['upc'])) {
                $tmp['upc'] = $v['upc'];
                $tmp['app_medicine_code_new'] = $v['code'];
                $code_data[] = $tmp;
            }
        }

        if (empty($code_data)) {
            return $this->error("参数错误：data内容错误", 701);
        }

        $type = $access_shop->type;

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif ($type === 2) {
            $meituan = app("mrx");
        } elseif ($type === 3) {
            $meituan = app("jay");
        } elseif ($type === 4) {
            $meituan = app("minkang");
        } elseif ($type === 5) {
            $meituan = app("qinqu");
        } else {
            return $this->error("系统错误", 701);
        }

        $params['app_poi_code'] = $mt_shop_id;
        $params['medicine_data'] = json_encode($code_data);

        $res = $meituan->medicineCodeUpdate($params);

        if ($res['data'] != 'ok') {
            \Log::info("[ERP接口]-[美团返回异常]-全部参数", $res);
            return $this->error($res['error']['msg'] ?? "", 3004);
        }

        return $this->success();
    }

    /**
     * 添加商品
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function add(Request $request)
    {
        \Log::info("[ERP接口]-[添加商品]-全部参数", $request->all());
        if (!$access_key = $request->get("access_key")) {
            return $this->error("参数错误：access_key必传", 701);
        }

        if (!$signature = $request->get("signature")) {
            return $this->error("参数错误：signature必传", 701);
        }

        if (!$timestamp = $request->get("timestamp")) {
            return $this->error("参数错误：timestamp必传", 701);
        }

        // if (($timestamp < time() - 300) || ($timestamp > time() + 300)) {
        //     return $this->error("参数错误：timestamp有误", 701);
        // }

        $receive_params = $request->get("data");

        if (empty($receive_params)) {
            return $this->error("参数错误：data不能为空", 701);
        }

        // 接收参数
        $shop_id = null;
        $data = [];
        $upcs = [];
        $http = new Client();
        $res_data = [];
        $res_data_items = [];

        foreach ($receive_params as $receive_param) {

            if (!isset($receive_param['shop_id'])) {
                return $this->error("参数错误：shop_id不存在", 701);
            }

            if ($shop_id === null) {
                $shop_id = $receive_param['shop_id'];
            }

            if (!isset($receive_param['app_medicine_code'])) {
                return $this->error("参数错误：app_medicine_code不存在", 701);
            }

            if (!isset($receive_param['upc'])) {
                return $this->error("参数错误：upc不存在", 701);
            }

            if (!isset($receive_param['price'])) {
                return $this->error("参数错误：price不存在", 701);
            }

            if (!isset($receive_param['stock'])) {
                return $this->error("参数错误：stock不存在", 701);
            }

            $data[$receive_param['shop_id']][] = [
                'app_poi_code' => $receive_param['shop_id'],
                'app_medicine_code' => $receive_param['app_medicine_code'],
                'upc' => $receive_param['upc'],
                'price' => $receive_param['price'],
                'stock' => $receive_param['stock'],
            ];
            $upcs[] = $receive_param['upc'];
        }


        if (!$access = ErpAccessKey::query()->where("access_key", $access_key)->first()) {
            return $this->error("参数错误：access_key错误", 701);
        }

        if (!$this->checkSing($request->only("access_key", "timestamp", "data", "signature"), $access->access_secret)) {
            \Log::info("[ERP接口]-[添加商品]-签名错误");
            return $this->error("签名错误", 703);
        }

        if (!empty($data)) {
            $upc_pluck = ErpDepot::whereIn("upc", $upcs)->pluck("second_code", "upc");
            foreach ($data as $shop_id => $v) {
                if (!$access_shop = ErpAccessShop::where(['shop_id' => $shop_id, 'access_id' => $access->id])->first()) {
                    \Log::info("[ERP接口]-[添加商品]-shop_id错误: {$shop_id}");
                    continue;
                }

                $type = $access_shop->type;
                $meituan = null;

                if ($type === 1) {
                    $meituan = app("yaojite");
                } elseif ($type === 2) {
                    $meituan = app("mrx");
                } elseif ($type === 3) {
                    $meituan = app("jay");
                } elseif ($type === 4) {
                    $meituan = app("minkang");
                } elseif ($type === 5) {
                    $meituan = app("qinqu");
                } else {
                    \Log::info("[ERP接口]-[添加商品]-门店 type 错误: {$type}");
                    continue;
                }

                if (!$category = ErpShopCategory::where("shop_id", $access_shop->id)->first()) {
                    \Log::info("[ERP接口]-[添加商品]-没有分类");

                    $erp_category_data = config("erp.categories");

                    foreach ($erp_category_data as $c_code => $c_name) {
                        $category_params = [
                            "app_poi_code" => $access_shop->mt_shop_id,
                            "category_code" => $c_code,
                            "category_name" => $c_name,
                            "sequence" => 100,
                        ];
                        $log = $meituan->medicineCatSave($category_params);
                        \Log::info("[ERP接口]-[添加商品]-[创建门店分类返回]: " . json_encode($log, JSON_UNESCAPED_UNICODE));
                    }

                    $c = new ErpShopCategory(
                        ['shop_id' => $access_shop->id]
                    );
                    $c->save();
                }
                $category_params = [
                    "app_poi_code" => $access_shop->mt_shop_id,
                    "category_code" => "9000000",
                    "category_name" => "未分类",
                    "sequence" => 100,
                ];
                $meituan->medicineCatSave($category_params);

                $params_data = [];
                $params_update_data = [];

                if (!is_null($meituan)) {
                    foreach ($v as $item) {
                        // $_tmp = [
                        //     "shop_id" => $shop_id,
                        //     'app_medicine_code' => $item['app_medicine_code'],
                        //     "status" => 1,
                        //     "msg" => "成功"
                        // ];
                        // if (isset($upc_pluck[$item['upc']])) {
                        //     $params_data[] = [
                        //         'app_medicine_code' => $item['app_medicine_code'],
                        //         'upc' => $item['upc'],
                        //         'price' => $item['price'],
                        //         'stock' => $item['stock'],
                        //         'category_code' => $upc_pluck[$item['upc']],
                        //         'is_sold_out' => 0,
                        //         'sequence' => 100
                        //     ];
                        // } else {
                            // $_tmp['status'] = 2;
                            // $_tmp['msg'] = "条码在品库中不存在";
                            // \Log::info("[ERP接口]-[添加商品]-UPC不存在: {$item['upc']}");
                        // }
                        $res_data_items[$item['app_medicine_code']] = [
                            "shop_id" => $shop_id,
                            'app_medicine_code' => $item['app_medicine_code'],
                            "status" => 1,
                            "msg" => "成功"
                        ];
                        // 添加数组
                        $params_data[] = [
                            'app_medicine_code' => $item['app_medicine_code'],
                            'upc' => $item['upc'],
                            'price' => $item['price'],
                            'stock' => $item['stock'],
                            'category_code' => isset($upc_pluck[$item['upc']]) ? $upc_pluck[$item['upc']] : 9000000,
                            'is_sold_out' => 0,
                            'sequence' => 100
                        ];
                        // 更新数组
                        $params_update_data[] = [
                            'app_medicine_code' => $item['app_medicine_code'],
                            'price' => $item['price'],
                            'stock' => $item['stock'],
                        ];
                    }
                    // $res_data = [
                    //     "service_key" => "HXFW_365",
                    //     "hx_parama" => $res_data_items
                    // ];
                    // \Log::info("海协ERP推送商品状态", $res_data);
                    // $response = $http->post("http://hxfwgw.drugwebcn.com/gateway/apiEntranceAction!apiEntrance.do", [RequestOptions::JSON => $res_data]);
                    // $result = json_decode($response->getBody(), true);
                    // \Log::info("海协ERP推送商品状态-返回", [$result]);
                    $params = [
                        "app_poi_code" => $access_shop->mt_shop_id,
                        "medicine_data" => json_encode($params_data, JSON_UNESCAPED_UNICODE)
                    ];
                    $params_update = [
                        "app_poi_code" => $access_shop->mt_shop_id,
                        "medicine_data" => json_encode($params_update_data, JSON_UNESCAPED_UNICODE)
                    ];
                    \Log::info("[ERP接口]-[添加商品]-创建药品参数", $params);
                    $create_log = $meituan->medicineBatchSave($params);
                    \Log::info("[ERP接口]-[添加商品]-[创建药品返回]: " . json_encode($create_log, JSON_UNESCAPED_UNICODE));
                    \Log::info("[ERP接口]-[添加商品]-更新药品参数", $params_update);
                    $update_log = $meituan->medicineBatchUpdate($params_update);
                    \Log::info("[ERP接口]-[添加商品]-[更新药品返回]: " . json_encode($update_log, JSON_UNESCAPED_UNICODE));

                    $msg = '';
                    if ($create_log['data'] === 'ok') {
                        $msg = $create_log['msg'] ?? '';
                    } else {
                        $msg = $create_log['error']['msg'] ?? '';
                    }
                    \Log::info("[ERP接口]-[添加商品]-[MSG]: " . $msg);
                    if ($msg) {
                        $msg = str_replace('批量添加药品结果：','',$msg);
                        \Log::info("[ERP接口]-[添加商品]-[MSG2]: " . $msg);
                        $msg_arr = json_decode($msg, true);
                        \Log::info("[ERP接口]-[添加商品]-[MSG-ARR]: ", [$msg_arr]);

                        if (!empty($msg_arr)) {
                            foreach ($msg_arr as $arr) {
                                if (mb_strpos($arr['error_msg'], '编码在该店中已存在') !== false) {
                                    $res_data_items[$arr['app_medicine_code']]['status'] = 2;
                                    $res_data_items[$arr['app_medicine_code']]['msg'] = '条码已存在';
                                    \Log::info("[ERP接口]-[添加商品]-[MSG-ARR-FAIL]: ", [$arr]);
                                }
                                if (mb_strpos($arr['error_msg'], '标品库中没有此药品') !== false) {
                                    $res_data_items[$arr['app_medicine_code']]['status'] = 2;
                                    $res_data_items[$arr['app_medicine_code']]['msg'] = '美团标品库中没有此药品';
                                    \Log::info("[ERP接口]-[添加商品]-[MSG-ARR-FAIL]: ", [$arr]);
                                }
                            }
                        }
                    }

                    $res_data_items = array_values($res_data_items);
                    $res_data = [
                        "service_key" => "HXFW_365",
                        "hx_parama" => $res_data_items
                    ];
                    \Log::info("海协ERP推送商品状态", $res_data);
                    $response = $http->post("http://hxfwgw.drugwebcn.com/gateway/apiEntranceAction!apiEntrance.do", [RequestOptions::JSON => $res_data]);
                    $result = json_decode($response->getBody(), true);
                    \Log::info("海协ERP推送商品状态-返回", [$result]);
                }
            }
        }

        \Log::info("[ERP接口]-[添加商品]-组合参数", $data);
        return $this->success();
    }

    /**
     * 校验签名
     * @param array $data
     * @param string $secret
     * @return bool
     * @author zhangzhen
     * @data dateTime
     */
    public function checkSing(array $params, string $secret)
    {
        $signature = $params["signature"];
        unset($params["signature"]);
        ksort($params);

        $waitSign = '';
        foreach ($params as $key => $item) {
            if (!empty($item)) {
                if (is_array($item)) {
                    $waitSign .= '&'.$key.'='.json_encode($item, JSON_UNESCAPED_UNICODE);
                } else {
                    $waitSign .= '&'.$key.'='.$item;
                }
            }
        }

        $waitSign = substr($waitSign, 1).$secret;
        \Log::info("[ERP接口]-[校验方法]-签名字符串：{$waitSign}");
        \Log::info("[ERP接口]-[校验方法]-md5字符串：".md5($waitSign));

        return $signature === md5($waitSign);
    }


    public function testStock(Request $request)
    {
        \Log::info("[ERP接口]-[测试同步库存]-全部参数", $request->all());
        if (!$access_key = $request->get("access_key")) {
            return $this->error("参数错误：access_key必传", 701);
        }

        if (!$signature = $request->get("signature")) {
            return $this->error("参数错误：signature必传", 701);
        }

        if (!$timestamp = $request->get("timestamp")) {
            return $this->error("参数错误：timestamp必传", 701);
        }

        if (($timestamp < time() - 300) || ($timestamp > time() + 300)) {
            return $this->error("参数错误：timestamp有误", 701);
        }

        $receive_params = $request->get("params");

        if (!isset($receive_params['shop_id'])) {
            return $this->error("参数错误：shop_id不存在", 701);
        }

        if (!isset($receive_params['data'])) {
            return $this->error("参数错误：data不存在", 701);
        }

        $shop_id = $receive_params['shop_id'];
        $data = $receive_params['data'];

        if (empty($data)) {
            return $this->error("参数错误：data不能为空", 701);
        }

        if (count($data) > 200) {
            return $this->error("参数错误：data内容不能超过200组", 701);
        }


        $medicine_data = [];

        foreach ($data as $v) {
            if (isset($v['code']) && isset($v['stock'])) {
                $tmp['app_poi_code'] = $shop_id;
                $tmp['app_medicine_code'] = $v['code'];
                $tmp['stock'] = $v['stock'];
                $medicine_data[] = $tmp;
            }
        }

        if (empty($medicine_data)) {
            return $this->error("参数错误：data内容错误", 701);
        }

        $params['app_poi_code'] = $shop_id;
        $params['medicine_data'] = json_encode($medicine_data);

        \Log::info("[ERP接口]-[测试同步库存]-请求参数", $params);

        return $this->success();
    }

    public function testAdd(Request $request)
    {
        \Log::info("[ERP接口]-[测试添加商品]-全部参数", $request->all());
        if (!$access_key = $request->get("access_key")) {
            return $this->error("参数错误：access_key必传", 701);
        }

        if (!$signature = $request->get("signature")) {
            return $this->error("参数错误：signature必传", 701);
        }

        if (!$timestamp = $request->get("timestamp")) {
            return $this->error("参数错误：timestamp必传", 701);
        }

        if (($timestamp < time() - 300) || ($timestamp > time() + 300)) {
            return $this->error("参数错误：timestamp有误", 701);
        }

        $receive_params = $request->get("data");

        $data = [];

        if (!empty($receive_params)) {
            foreach ($receive_params as $receive_param) {

                if (!isset($receive_param['shop_id'])) {
                    return $this->error("参数错误：shop_id不存在", 701);
                }

                if (!isset($receive_param['app_medicine_code'])) {
                    return $this->error("参数错误：app_medicine_code不存在", 701);
                }

                if (!isset($receive_param['upc'])) {
                    return $this->error("参数错误：upc不存在", 701);
                }

                if (!isset($receive_param['price'])) {
                    return $this->error("参数错误：price不存在", 701);
                }

                if (!isset($receive_param['stock'])) {
                    return $this->error("参数错误：stock不存在", 701);
                }

                $data[] = [
                    'app_poi_code' => $receive_param['shop_id'],
                    'app_medicine_code' => $receive_param['app_medicine_code'],
                    'upc' => $receive_param['upc'],
                    'price' => $receive_param['price'],
                    'stock' => $receive_param['stock'],
                ];
            }
        }

        \Log::info("[ERP接口]-[测试添加商品]-组合参数", $data);

        return $this->success();
    }
}
