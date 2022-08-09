<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\UserWebIm;
use Illuminate\Http\Request;

class WebIMController extends Controller
{
    public function mt_chain(Request $request)
    {
        $user_id = $request->user()->id;

        if (!$im = UserWebIm::where('user_id', $user_id)->first()) {
            return $this->error('该账号没有权限访问');
        }
        $mt = app('meiquan');
        $url = $mt->webim_index($user_id, 2, $im->auth);
        return $this->success(['url' => $url]);
    }

    public function mt_shop(Request $request)
    {
        $shop_id = $request->get('shop_id', 0);
        $user_id = $request->user()->id;

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        if ($shop->user_id != $user_id) {
            if (!$request->user()->hasPermissionTo('currency_shop_all')) {
                return $this->error('无权限操作此门店');
            }
        }
        $mt = null;
        if ($shop->meituan_bind_platform == 4) {
            $mt = app('minkang');
        } elseif ($shop->meituan_bind_platform == 31) {
            $mt = app('meiquan');
        }

        if (!$mt) {
            return $this->error('该门店不支持IM聊天');
        }
        $url = $mt->webim_index($user_id, 1, $shop->waimai_mt);
        return $this->success(['url' => $url]);
    }
}
