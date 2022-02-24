<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanSanFang;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public $prefix = '[美团外卖三方服务商-绑定门店回调]';

    public function bind(Request $request)
    {
        $this->prefix .= '-[绑定]';

        if ($poiId = $request->get("ePoiId", "")) {
            $this->log('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }

    public function unbound(Request $request)
    {
        $this->prefix .= '-[解绑]';

        if ($poiId = $request->get("ePoiId", "")) {
            $this->log('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }
}
