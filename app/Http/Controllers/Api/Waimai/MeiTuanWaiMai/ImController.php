<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Traits\LogTool2;
use Illuminate\Http\Request;

class ImController
{
    use LogTool2;

    public $prefix_title = '[美团外卖回调&###]';

    public function create(Request $request, $platform)
    {
        if (!$biz_type = $request->get("biz_type")) {
            return json_encode(['data' => 'ok']);
        }
        $this->log_tool2_prefix = str_replace('###', get_meituan_develop_platform($platform) . "&Im消息推送|biz_type:{$biz_type}", $this->prefix_title);
        $this->log_info('全部参数', $request->all());
        $push_content_str = $request->get('push_content', '');
        $push_content = json_decode($push_content_str, true);
        $this->log_info('美团外卖统一接口');
        $app_poi_code = $push_content['app_poi_code'];
        $this->log_info("美团门店ID:{$app_poi_code}");
    }
}
