<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Shop;
use App\Models\ShopShipper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShanSongAuthController extends Controller
{
    public $prefix = '[闪送服务商授权回调]';

    public function auth(Request $request)
    {
        if (!$code = $request->get('code')) {
            return '授权失败，不支持全部绑定，请检查是否单个门店绑定';
        }
        $this->log('全部参数', $request->all());
        if (!$shop_id = $request->get('thirdStoreId')) {
            return '授权失败，不支持全部绑定，请检查是否单个门店绑定';
        }
        if (!$ss_shop_id = $request->get('shopId')) {
            return '授权失败，不支持全部绑定，请检查是否单个门店绑定';
        }

        $ss = new ShanSongService(config('ps.shansongservice'));
        $res = $ss->get_token_by_code($code);

        if (!isset($res['data']['access_token'])) {
            $this->log("获取Token失败", is_array($res) ? $res : [$res]);
            return $res['msg'] ?? '授权失败，请稍后再试';
        }

        $access_token = $res['data']['access_token'];
        $refresh_token = $res['data']['refresh_token'];
        $expires_in = $res['data']['expires_in'];

        if (!$shop = Shop::find($shop_id)) {
            return '授权门店没有找到，稍后再试';
        }

        if (ShopShipper::where('shop_id', $shop_id)->where('platform', 3)->first()) {
            ShopShipper::where('shop_id', $shop_id)->where('platform', 3)->update([
                'three_id' => $ss_shop_id,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'expires_in' => $expires_in,
                'token_time' => date("Y-m-d H:i:s"),
            ]);
        } else {
            if ($shipper = ShopShipper::where('three_id', $ss_shop_id)->first()) {
                $this->log('闪送授权，门店ID已存在，已删除', $shipper->toArray());
                $shipper->delete();
                $old_key = 'ss:shop:auth:' . $shipper->shop_id;
                $old_key_ref = 'ss:shop:auth:ref:' . $shipper->shop_id;
                Cache::forget($old_key);
                Cache::forget($old_key_ref);
            }
            ShopShipper::create([
                'user_id' => $shop->user_id,
                'shop_id' => $shop->id,
                'platform' => 3,
                'three_id' => $ss_shop_id,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'expires_in' => $expires_in,
                'token_time' => date("Y-m-d H:i:s"),
            ]);
        }

        $key = 'ss:shop:auth:' . $shop_id;
        $key_ref = 'ss:shop:auth:ref:' . $shop_id;
        Cache::put($key, $access_token, $expires_in - 100);
        Cache::forever($key_ref, $refresh_token);

        $this->log("获取Token成功|access_token:{$access_token},refresh_token:{$refresh_token},expires_in:{$expires_in}");
        return '授权成功';
    }
}
