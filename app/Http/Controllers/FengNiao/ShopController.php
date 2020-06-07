<?php


namespace App\Http\Controllers\FengNiao;


use Illuminate\Http\Request;

class ShopController
{
    public function status(Request $request)
    {
        \Log::info('蜂鸟门店状态回调Request', [$request->all()]);

        if (!$data_str = $request->get('data', '')) {
            return [];
        }

        $data = json_decode(urldecode($data_str), true);

        if (empty($data)) {
            return [];
        }

        // 商家门店ID
        $shop_id = $data['chain_store_code'] ?? '';
        // 变更类型：0-开关店,1-配送范围
        $type = $data['option_type'] ?? '';
        
        \Log::info('蜂鸟门店状态回调', compact('shop_id', 'type'));


        return [];
    }
}