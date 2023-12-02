<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanSanFang;

use App\Http\Controllers\Controller;
use App\Models\MeituanOpenToken;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShopController extends Controller
{
    public $prefix_title = '[美团外卖三方服务商-门店回调-###]';

    public function bind(Request $request)
    {
        $this->prefix = str_replace('###', '绑定', $this->prefix_title);
        $token = $request->get("appAuthToken", "");
        $shop_id = $request->get("ePoiId", "");
        $mt_shop_id = $request->get("poiId", "");

        if ($token && $shop_id) {
            $this->log('全部参数', $request->all());
            if ($shop = Shop::where('mtwm', $shop_id)->first()) {
                $shop->meituan_bind_platform = 25;
                $shop->waimai_mt = $shop_id;
                $shop->bind_date = date("Y-m-d H:i:s");
                $shop->save();
                if ($token_data = MeituanOpenToken::where('shop_id', $shop_id)->first()) {
                    $token_data->update(['token' => $token]);
                } else {
                    MeituanOpenToken::create([
                        'shop_id' => $shop_id,
                        'mt_shop_id' => $mt_shop_id,
                        'token' => $token,
                    ]);
                }
                $key = 'meituan:open:token:' . $shop_id;
                Cache::put($key, $token);
            } else {
                $this->log("绑定门店不存在");
            }
        }

        return json_encode(['data' => 'OK']);
    }

    public function unbound(Request $request)
    {
        $this->prefix = str_replace('###', '解绑', $this->prefix_title);
        $shop_id = $request->get("ePoiId", "");

        if ($shop_id) {
            if ($shop = Shop::where('waimai_mt',$shop_id)) {
                $shop->waimai_mt = '';
                $shop->save();
                $this->log('全部参数', $request->all());
                MeituanOpenToken::where('shop_id', $shop_id)->delete();
                $key = 'meituan:open:token:' . $shop_id;
                Cache::forget($key);
            }
        }

        return json_encode(['data' => 'OK']);
    }
}
