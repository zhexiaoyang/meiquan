<?php

namespace App\Http\Controllers\Api\Waimai\MinKang;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public $prefix_title = '[美团外卖民康-商品回调-###]';

    public function create(Request $request)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode($medicine_data, true);
        $app_poi_code = $data['app_poi_code'] ?? '';

        if ($app_poi_code) {
            $this->prefix = str_replace('###', "创建商品|门店ID:{$app_poi_code}", $this->prefix_title);
            $this->log('全部参数', $request->all());
        }
        return json_encode(['data' => 'ok']);
    }

    public function update(Request $request)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode($medicine_data, true);
        $app_poi_code = $data['app_poi_code'] ?? '';

        if ($app_poi_code) {
            $this->prefix = str_replace('###', "修改商品|门店ID:{$app_poi_code}", $this->prefix_title);
            $this->log('全部参数', $request->all());
        }
        return json_encode(['data' => 'ok']);
    }

    public function delete(Request $request)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode($medicine_data, true);
        $app_poi_code = $data['app_poi_code'] ?? '';

        if ($app_poi_code) {
            $this->prefix = str_replace('###', "删除商品|门店ID:{$app_poi_code}", $this->prefix_title);
            $this->log('全部参数', $request->all());
        }
        return json_encode(['data' => 'ok']);
    }
}
