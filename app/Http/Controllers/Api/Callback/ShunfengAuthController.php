<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopShipper;
use App\Traits\LogTool;
use Illuminate\Http\Request;

class ShunfengAuthController extends Controller
{
    use LogTool;

    public $prefix_title = '[顺丰服务商授权回调$###]';

    public function auth(Request $request)
    {
        $this->prefix = str_replace('###', '绑定', $this->prefix_title);
        if (!$shop_id = $request->get('out_shop_id')) {
            $this->log_info('授权失败，没有获取到门店信息，不支持个人账户授权');
            return false;
        }
        if (!$shop_id_sf = $request->get('shop_id')) {
            $this->log_info('授权失败，没有获取到门店信息，不支持个人账户授权');
            return false;
        }

        $this->log_info('全部参数', $request->all());

        if (!$shop = Shop::find($shop_id)) {
            $this->log_info('门店不存在');
            return false;
        }

        if (ShopShipper::where('shop_id', $shop_id)->where('platform', 7)->first()) {
            ShopShipper::where('shop_id', $shop_id)->where('platform', 7)->update([
                'three_id' => $shop_id_sf,
                'token_time' => date("Y-m-d H:i:s"),
            ]);
        } else {
            if ($shipper = ShopShipper::where('three_id', $shop_id_sf)->where('platform', 7)->first()) {
                $this->log_info('顺丰授权，门店ID已存在，已删除', $shipper->toArray());
                $shipper->delete();
            }
            ShopShipper::create([
                'user_id' => $shop->user_id,
                'shop_id' => $shop->id,
                'platform' => 7,
                'three_id' => $shop_id_sf,
            ]);
            if ($shop->shop_id_sf) {
                $this->log_info('达达授权，达达门店ID已存在，已清除：' . $shop->shop_id_ss);
                $shop->shop_id_sf = '';
                $shop->save();
            }
        }
        $this->log_info('授权成功');

        return json_encode(['error_code' => 0, 'error_msg' => 'success']);
    }
}
