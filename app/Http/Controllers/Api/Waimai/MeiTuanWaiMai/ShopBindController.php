<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Models\Shop;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Illuminate\Http\Request;

class ShopBindController
{
    use LogTool, NoticeTool;

    public $prefix_title = '[美团外卖门店绑定回调&###]';

    // 民康应用-门店绑定授权-正在使用
    public function status(Request $request, $platform)
    {
        $info = json_decode(urldecode($request->get('poi_info')), true);
        $type = $request->get('op_type');
        $mt_shop_id = $info['appPoiCode'];
        if (!empty($request->all())) {
            $this->log_info('全部参数', $request->all());
        }
        if ($type && $mt_shop_id) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&类型:{$type}&美团ID:{$mt_shop_id}", $this->prefix_title);
            // 查询门店个数
            $shops = Shop::query()->where("mtwm", $mt_shop_id)->get();
            if ($shop = $shops->first()) {
                if ($shops->count() > 1) {
                    $this->ding_error("美团外卖ID，数量大于1");
                    return json_encode(['data' => 'ok']);
                }
                if ($type == 1) {
                    // 绑定
                    if ($shop->waimai_mt) {
                        $this->ding_error("该门店已经绑定");
                        return json_encode(['data' => 'ok']);
                    } else {
                        $shop->waimai_mt = $mt_shop_id;
                        $shop->meituan_bind_platform = 4;
                        $shop->bind_date = date("Y-m-d H:i:s");
                        $shop->save();
                        $this->log_info("绑定成功");
                    }
                } elseif ($type == 2) {
                    // 解绑
                    if ($shop->waimai_mt) {
                        $shop->waimai_mt = '';
                        $shop->unbind_date = date("Y-m-d H:i:s");
                        $shop->save();
                        $this->log_info("解绑成功");
                    }
                }
            } else {
                $this->log_info("没有找到门店");
            }
        }

        return json_encode(['data' => 'ok']);
    }
}
