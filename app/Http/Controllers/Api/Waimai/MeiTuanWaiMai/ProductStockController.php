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
        $xuesong = ['9493159','9493161','9493163','9493216','9493164','9492506','9493165','9493089','9493167','9492507',
            '9492509','9493168','9493172','9492664','9492666','9492670','9492671'];
        $wanxiang_mqp = ['12606969' => '0009','12965411' => '0004','12606971' => '0015','12966872' => '0012', '13084144' => '0003',
            '13144836' => '0006','12931358' => '0007','12931400' => '0007','13778180' => '0007','12931402' => '0007','13505397' => '0007'];
        if (!in_array($shop_id, array_merge($wanxiang, $xuesong))) {
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
            $hd_shop_id = $wanxiang_mqp[$shop_id];
            $this->log_info("万祥商品库存拉取,万祥门店ID:{$hd_shop_id}", $product_ids);
            $product_in = implode(',', $product_ids);
            $data = DB::connection('wanxiang_haidian')
                ->select("SELECT 药品ID as id,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$hd_shop_id}' AND [药品ID] IN ({$product_in})");
            if (!empty($data)) {
                $res_data = [];
                foreach ($data as $v) {
                    $res_data[] = [
                        'app_medicine_code' => $v->id,
                        'stock' => intval($v->stock),
                    ];
                }
                if (!empty($res_data)) {
                    $this->log_info("万祥商品库存拉取,万祥门店ID:{$hd_shop_id},返回值", $res_data);
                    return json_encode(["code" => 0, "message" => "成功", "data" => $res_data]);
                }
            }
        }

        if (in_array($shop_id, $xuesong)) {
            $xs_shop_id = $shop_id;
            $this->log_info("雪松商品库存拉取,雪松门店ID:{$xs_shop_id}", $product_ids);
            $product_in = implode(',', $product_ids);
            // $data = DB::connection('xuesong')
            //     ->select("SELECT 药品ID as id,库存 as stock FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$hd_shop_id}' AND [药品ID] IN ({$product_in})");
            $data = DB::connection('xuesong')
                ->select("SELECT bianhao as id, kucun as stock FROM [dbo].[v_meituan_kucun] WHERE [meituan] = N'{$xs_shop_id}' AND [bianhao] IN ({$product_in})");
            if (!empty($data)) {
                $res_data = [];
                foreach ($data as $v) {
                    $res_data[] = [
                        'app_medicine_code' => $v->id,
                        'stock' => intval($v->stock),
                    ];
                }
                if (!empty($res_data)) {
                    $this->log_info("雪松商品库存拉取,雪松门店ID:{$xs_shop_id},返回值", $res_data);
                    return json_encode(["code" => 0, "message" => "成功", "data" => $res_data]);
                }
            }
        }

        if ($shop_id == '13676234') {
            $this->log_info('公园道店全部参数', $request->all());
        }

        return json_encode(["code" => 1, "message" => ""]);
    }

}
