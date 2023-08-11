<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * 用户创建的门店列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->account_shop_id) {
            return $this->success([Shop::select('id', 'shop_name', 'wm_shop_name')->find($user->account_shop_id)]);
        }
        $shops = Shop::select('id', 'shop_name', 'running_select as checked','shop_lng','shop_lat','shop_address')->where('user_id', $user->id)->get();

        if (!empty($shops)) {
            // 默认选择门店ID
            $select_id = 0;
            foreach ($shops as $shop) {
                if (!$shop->wm_shop_name) {
                    $shop->wm_shop_name = $shop->shop_name;
                }
                if ($shop->checked) {
                    if ($select_id) {
                        // 已经有默认值了
                        $shop->checked = 0;
                    } else {
                        // 设置默认选择门店ID
                        $select_id = $shop->id;
                    }
                }
            }
            if (!$select_id) {
                // 没有门店选择，默认第一个选中
                $shops[0]->checked = 1;
            }
        }

        return $this->success($shops);
    }
}
