<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopThreeId;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $search_key = $request->get("search_key", "");

        $query = Shop::select("id", "shop_name", "city");

        if ($search_key) {
            $query->where("shop_name", "like", "%{$search_key}%");
        }

        $shops = $query->orderByDesc("id")->get();

        return $this->success($shops);
    }

    /**
     * 审核管理-三方门店ID审核
     * @data 2021/12/1 4:27 下午
     */
    public function apply_three_id_shops(Request $request)
    {
        $query = ShopThreeId::with(['shop' => function ($query) {
            $query->select('id', 'shop_name', 'contact_name', 'contact_phone');
        }]);

        $data = $query->get();

        return $this->success($data);
    }

    public function apply_three_id_save(Request $request)
    {
        $id = $request->get('id', 0);
        $status = $request->get('status', 0);

        if (!$apply = ShopThreeId::find($id)) {
            return $this->error('门店不存在');
        }

        if ($status == 1) {
            if (!$shop = Shop::query()->find($apply->shop_id)) {
                return $this->error('门店不存在');
            }

            if (($mtwm = $apply->mtwm) && !$shop->mtwm) {
                $shop->mtwm = $mtwm;
                $shop->chufang_mt = $mtwm;
                // $shop->chufang_status = 2;
            }
            if (($ele = $apply->ele) && !$shop->ele) {
                $shop->ele = $ele;
                $shop->chufang_ele = $ele;
                // $shop->chufang_status = 2;
            }
            if (($jddj = $apply->jddj) && !$shop->jddj) {
                $shop->jddj = $jddj;
                $shop->chufang_jddj = $ele;
                // $shop->chufang_status = 2;
            }

            $shop->save();
            $apply->delete();
        } else {
            $apply->delete();
        }

        return $this->success();
    }

    /**
     * 管理员修改三方ID（已作废）
     * @data 2021/12/1 4:20 下午
     */
    public function update_three(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }

        $mtwm = $request->get('mtwm', '');
        $ele = $request->get('ele', '');
        $jddj = $request->get('jddj', '');

        $shop->mtwm = $mtwm;
        $shop->ele = $ele;
        $shop->jddj = $jddj;

        if ($mtwm) {
            $shop->chufang_mt = $mtwm;
            $shop->chufang_status = 2;
        }
        if ($ele) {
            $shop->chufang_ele = $ele;
            $shop->chufang_status = 2;
        }

        $shop->save();

        return $this->success($shop);
    }
}
