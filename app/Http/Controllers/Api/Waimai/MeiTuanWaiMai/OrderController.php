<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public $prefix_title = '[美团外卖回调&###]';

    public function remind(Request $request)
    {
        $text = "催单";
        if ($platform = $request->get('platform')) {
            $platforms = ['','民康','寝趣','洁爱眼'];
            $text = $platforms[$platform] . '@' . $text;
        }
        $this->prefix = str_replace('###', $text, $this->prefix_title);

        if ($order_id = $request->get("order_id", "")) {
            $this->log('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }

    public function down(Request $request)
    {
        $this->prefix = str_replace('###', "隐私号降级", $this->prefix_title);

        $data = $request->all();

        if (!empty($data)) {
            $this->log('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }
}
