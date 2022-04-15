<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use Illuminate\Http\Request;
use App\Traits\LogTool;
use Illuminate\Support\Facades\DB;

class ProductStockController
{
    use LogTool;

    public $prefix_title = '[美团外卖库存回调&###]';

    public function stock(Request $request, $platform)
    {
        if (!$shop_id = $request->get('app_poi_code')) {
            return json_encode(["data" => "ok"]);
        }
        $wanxiang = ['12606969','12965411','12606971','12966872','13084144','13144836','12931358','12931400','13778180','12931402','13505397'];
        if (!in_array($shop_id, array_merge($wanxiang))) {
            return json_encode(["code" => 1, "message" => ""]);
        }
        $product_ids = [];
        // 日志格式
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&门店:{$shop_id}", $this->prefix_title);
        if ($str = $request->get('app_food_list')) {
            $ids = json_decode(urldecode($str), true);
            if (is_array($ids) && !empty($ids)) {
                foreach ($ids as $v) {
                    $product_ids[] = $v['app_food_code'];
                }
                // $this->log_info('app_food_list商品信息', $ids);
            }
        }
        if ($str = $request->get('medicine_code_list')) {
            $ids = json_decode(urldecode($str), true);
            if (is_array($ids) && !empty($ids)) {
                $product_ids = $ids;
                // $this->log_info('medicine_code_list商品信息', $ids);
            }
        }
        if (empty($product_ids)) {
            return json_encode(["code" => 1, "message" => ""]);
        }

        if (in_array($shop_id, $wanxiang)) {
            $this->log_info('万祥商品库存拉取', $product_ids);
            // $data = DB::connection('wanxiang_haidian')
            //     ->select("SELECT 药品ID as id,upc,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'0006' AND [upc] <> '' AND [upc] IS NOT NULL");
            // if (!empty($data)) {
            //
            // }
        }

        return json_encode(["data" => "ok"]);
    }

}
