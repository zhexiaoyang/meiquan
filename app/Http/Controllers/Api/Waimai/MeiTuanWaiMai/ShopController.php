<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Models\Shop;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Illuminate\Http\Request;

class ShopController
{
    use LogTool, NoticeTool;

    public $prefix_title = '[美团外卖回调&###]';

    public function status(Request $request, $platform)
    {
        $mt_shop_id = $request->get("app_poi_code", "");
        $status = $request->get("poi_status", "");
        if ($mt_shop_id && $status) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&门店状态变更|门店:{$mt_shop_id}|状态:{$status}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
            // 查询门店个数
            $shops = Shop::query()->where("waimai_mt", $mt_shop_id)->get();
            if ($shop = $shops->first()) {
                if ($shops->count() > 1) {
                    $this->ding_error("数量大于1");
                    return '';
                }
                // 门店当前状态，参考值：121-营业；120-休息；18-上线；19-下线。
                if ($status == 121) {
                    $shop->mt_open = 1;
                    $shop->save();
                } elseif ($status == 120) {
                    $shop->mt_open = 3;
                    $shop->save();
                } elseif ($status == 18) {
                    $shop->mt_online = 1;
                    $shop->save();
                } elseif ($status == 19) {
                    $shop->mt_online = 0;
                    $shop->save();
                }
            } else {
                $this->log_info("门店状态变更没有找到门店");
            }
        }

        return json_encode(['data' => 'ok']);
    }
}
