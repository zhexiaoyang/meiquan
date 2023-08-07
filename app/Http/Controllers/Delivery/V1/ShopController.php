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
            return $this->success([Shop::select('id', 'shop_name')->find($user->account_shop_id)]);
        }
        $shops = Shop::select('id', 'shop_name')->where('user_id', $user->id)->get();

        return $this->success($shops);
    }
}
