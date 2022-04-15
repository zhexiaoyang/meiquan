<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use Illuminate\Http\Request;
use App\Traits\LogTool;

class ProductStockController
{
    use LogTool;

    public $prefix_title = '[美团外卖库存回调&###]';

    public function stock(Request $request, $platform)
    {
        if (!$shop_id = $request->get('app_poi_code')) {
            return json_encode(["data" => "ok"]);
        }
        // 日志格式
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&门店:{$shop_id}", $this->prefix_title);
        if ($str = $request->get('medicine_code_list')) {
            $ids = json_encode(urldecode($str), true);
            if (is_array($ids) && !empty($ids)) {
                $this->log_info('商品信息', $ids);
            }
        }
    }

}
