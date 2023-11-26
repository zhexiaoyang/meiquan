<?php

namespace App\Http\Controllers\Api\Waimai\Ele;

use App\Http\Controllers\Controller;
use App\Libraries\ElemeOpenApi\Api\RpcService;
use App\Libraries\ElemeOpenApi\Api\ShopService;
use App\Libraries\ElemeOpenApi\Config\Config;
use App\Libraries\ElemeOpenApi\OAuth\OAuthClient;
use App\Models\EleOpenToken;
use App\Models\Shop;
use Illuminate\Http\Request;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    use NoticeTool, LogTool;

    public function index(Request $request)
    {
        if (!$code = $request->get('code')) {
            return response()->json(['error' => '授权失败']);
        }
        if (!$state = (int) $request->get('state')) {
            return response()->json(['error' => '授权失败']);
        }
        $config = new Config(config('ps.ele_open.sandbox_key'), config('ps.ele_open.sandbox_secret'), config('ps.ele_open.sandbox'));
        $client = new OAuthClient($config);
        $auth_res = $client->get_token_by_code($code, config('ps.ele_open.callback_url'));
        if (!empty($auth_res->access_token)) {
            Log::channel('ele-open')->info('请求token失败|授权返回参数：' . json_encode($request->all()));
            return response()->json(['error' => '授权失败']);
        }
        if (!$shop = Shop::where('waimai_ele', $state)->first()) {
            return response()->json(['error' => '授权失败']);
        }
        $access_token = $auth_res->access_token;
        $refresh_token = $auth_res->refresh_token;
        $expires_in = $auth_res->expires_in;
        $shop_client = new ShopService($access_token, $config);
        $shop_info = $shop_client->get_shop($state);
        if (empty($shop_info)) {
            return response()->json(['error' => '授权失败']);
        }
        if (!$shop->wm_shop_name) {
            $shop->wm_shop_name = $shop_info->name;
        }
        $shop->ele_shop_name = $shop_info->name;
        $shop->ele_bind = 2;
        $shop->bind_ele_date = date("Y-m-d H:i:s");
        $shop->save();
        $key = 'ele:shop:token:' . $state;
        $key_ref = 'ele:shop:token:ref:' . $state;
        Cache::put($key, $access_token, $expires_in - 30);
        Cache::put($key_ref, $refresh_token, $expires_in - 30);
        EleOpenToken::create([
            'shop_id' => $shop->id,
            'ele_shop_id' => $state,
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => $expires_in,
            'expires_at' => date("Y-m-d H:i:s", time() + $expires_in),
        ]);
        return response()->json(['message' => '授权成功']);
    }

}
