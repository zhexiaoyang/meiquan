<?php


namespace App\Http\Controllers\FengNiao;


use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController
{
    public function status(Request $request)
    {
        \Log::info('蜂鸟门店状态回调-全部参数', $request->all());

        if (!$data_str = $request->get('data', '')) {
            return [];
        }

        $data = json_decode(urldecode($data_str), true);

        if (empty($data)) {
            return [];
        }

        $res = ['status' => 200, 'msg' => '', 'data' => ''];

        // 商家门店ID
        $shop_id = $data['chain_store_code'] ?? '';
        // 变更类型：0-开关店,1-配送范围
        $type = $data['option_type'] ?? '';

        if (($shop = Shop::where('id', $shop_id)->first()) && in_array($type, [0, 1])) {
            if ($type == 1) {
                $shop->status = 40;
                $shop->shop_id_fn = $shop->id;
            } else {
                $shop->shop_id_fn = '';
            }
            if ($shop->save()) {
                // 发送短信操作
            }
        }

        \Log::info('蜂鸟门店状态回调-部分参数', compact('shop_id', 'type'));

        return json_encode($res);
    }
}
