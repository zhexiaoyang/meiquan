<?php

namespace App\Http\Controllers\Erp\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAccessKey;
use App\Models\ErpAccessShop;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function stock(Request $request)
    {
        \Log::info("接口参数", $request->all());
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

        if (!$access = ErpAccessKey::query()->where("access_key", $access_key)->first()) {
            return $this->error("参数错误：access_key错误", 701);
        }

        if (!$access_shop = ErpAccessShop::query()->where(['shop_id' => $shop_id, 'access_id' => $access->id])->first()) {
            return $this->error("参数错误：shop_id错误", 701);
        }

        if (!$mt_shop_id = $access_shop->mt_shop_id) {
            return $this->error("系统错误", 701);
        }

        if (!$this->checkSing($request->only("access_key", "timestamp", "params", "signature"), $access->access_secret)) {
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

    public function checkSing(array $data, string $secret)
    {
        $signature = $data["signature"];

        unset($data["signature"]);

        $seed = 'access_key=' . $data['access_key'] . '&params=' . json_encode($data['params'], JSON_UNESCAPED_UNICODE) . '&timestamp=' . $data['timestamp'] . $secret;
        \Log::info($seed);

        return $signature === md5($seed);
    }
}
