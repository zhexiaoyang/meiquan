<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Libraries\DaDaService\DaDaService;
use App\Models\Shop;
use App\Models\ShopShipper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DaDaAuthController extends Controller
{
    public $prefix = '[达达服务商授权回调]';

    public function auth(Request $request)
    {
        $ticket = $request->get('ticket', '');
        if (!$source_id = $request->get('sourceId')) {
            return '授权失败，商户ID不存在';
        }
        $this->log('全部参数', $request->all());
        if (!$shop_no = $request->get('shopNo')) {
            return '授权失败，达达门店ID不存在';
        }
        if (!$shop_id = $request->get('state')) {
            return '授权失败，门店ID不存在';
        }

        $shop_id = Cache::get('dadaticket:' . $ticket);
        if (!$shop_id) {
            return '授权失败，门店ID不存在';
        }

        $dada = new DaDaService(config('ps.dada'));
        $dada_res = $dada->get_auth_status($ticket);
        $source_id = $dada_res['result']['sourceId'] ?? '';
        $shop_no = $dada_res['result']['shopNo'] ?? '';
        if (!$source_id || !$shop_no) {
            return '授权失败，未获取到授权信息';
        }

        if (!$shop = Shop::find($shop_id)) {
            return '授权门店没有找到，稍后再试';
        }
        if (ShopShipper::where('shop_id', $shop_id)->where('platform', 5)->first()) {
            ShopShipper::where('shop_id', $shop_id)->where('platform', 5)->update([
                'three_id' => $shop_no,
                'source_id' => $source_id,
                'token_time' => date("Y-m-d H:i:s"),
            ]);
        } else {
            if ($shipper = ShopShipper::where('three_id', $shop_no)->where('platform', 5)->first()) {
                $this->log('达达授权，门店ID已存在，已删除', $shipper->toArray());
                $shipper->delete();
            }
            ShopShipper::create([
                'user_id' => $shop->user_id,
                'shop_id' => $shop->id,
                'platform' => 5,
                'three_id' => $shop_no,
                'source_id' => $source_id,
                'token_time' => date("Y-m-d H:i:s"),
            ]);
            if ($shop->shop_id_dd) {
                $this->log('达达授权，达达门店ID已存在，已清除：' . $shop->shop_id_ss);
                $shop->shop_id_dd = '';
                $shop->save();
            }
        }

        return '授权成功';
    }
}
