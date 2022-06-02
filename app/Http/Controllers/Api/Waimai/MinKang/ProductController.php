<?php

namespace App\Http\Controllers\Api\Waimai\MinKang;

use App\Models\Shop;
use App\Traits\LogTool;
use Illuminate\Http\Request;

class ProductController
{
    use LogTool;

    public $prefix_title = '[美团外卖民康-商品回调-###]';

    public function create(Request $request)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode(urldecode($medicine_data), true);
        $app_poi_code = $data[0]['app_poi_code'] ?? '';

        if ($app_poi_code) {
            $this->prefix = str_replace('###', "创建商品|门店ID:{$app_poi_code}", $this->prefix_title);
            $this->log_info('全部参数', $request->all());
        }
        return json_encode(['data' => 'ok']);
    }

    public function update(Request $request)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode(urldecode($medicine_data), true);
        $app_poi_code = $data[0]['app_poi_code'] ?? '';

        if ($app_poi_code) {
            $this->prefix = str_replace('###', "修改商品|门店ID:{$app_poi_code}", $this->prefix_title);
            $this->log_info('全部参数', $request->all());
        }
        return json_encode(['data' => 'ok']);
    }

    public function delete(Request $request)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode(urldecode($medicine_data), true);
        $app_poi_code = $data[0]['app_poi_code'] ?? '';

        if (!$app_poi_code) {
            return json_encode(['data' => 'ok']);
        }
        if ($shop = Shop::select('id')->where('waimai_mt', $app_poi_code)->first()) {

        }
        return json_encode(['data' => 'ok']);
    }
}
