<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Traits\LogTool;
use Illuminate\Http\Request;

class ShopController
{
    use LogTool;

    public $prefix_title = '[美团外卖回调&###]';

    public function status(Request $request, $platform)
    {
        if ($shop_id = $request->get("app_poi_code", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&门店状态|门店:{$shop_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }
}
