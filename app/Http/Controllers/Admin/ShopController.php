<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\ShopExport;
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

    public function export(Request $request, ShopExport $export)
    {
        $user = $request->user();
        if (!in_array($user->id, [1,32])) {
            return $this->error('没有权限');
        }
        return $export->withRequest();
    }

    /**
     * 审核管理-三方门店ID审核
     * @data 2021/12/1 4:27 下午
     */
    public function apply_three_id_shops(Request $request)
    {
        $query = ShopThreeId::with(['shop' => function ($query) {
            $query->select('id', 'shop_name', 'contact_name', 'contact_phone');
        },'conflict_mt' => function ($query) {
            $query->select('id', 'mtwm', 'shop_name', 'contact_name', 'contact_phone')->where('mtwm', '<>', '');
        },'conflict_ele' => function ($query) {
            $query->select('id', 'ele', 'shop_name', 'contact_name', 'contact_phone')->where('ele', '<>', '');
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
                if ($mtwm && ($_shop = Shop::query()->where('mtwm', $mtwm)->first())) {
                    return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
                $shop->mtwm = $mtwm;
                $shop->chufang_mt = $mtwm;
                // $shop->chufang_status = 2;
            }
            if (($ele = $apply->ele) && !$shop->ele) {
                if ($ele && ($_shop = Shop::query()->where('ele', $ele)->first())) {
                    return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
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
     * 管理员修改三方ID
     * @data 2021/12/1 4:20 下午
     */
    public function update_three(Request $request)
    {
        $shop_id = $request->get('id', 0);

        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error('门店不存在');
        }


        if (ShopThreeId::where('shop_id', $shop_id)->first()) {
            return $this->error('该门店有待审核ID，请先审核');
        }

        $mtwm = $request->get('mtwm');
        $ele = $request->get('ele');
        $jddj = $request->get('jddj');

        if (!is_null($mtwm)) {
            if ($mtwm && ($_shop = Shop::query()->where('mtwm', $mtwm)->first())) {
                return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
            $shop->mtwm = $mtwm;
            if ($shop->second_category == 200001) {
                $shop->chufang_mt = $mtwm;
                $shop->chufang_status = 2;
            }
        }
        if (!is_null($ele)) {
            if ($ele && ($_shop = Shop::query()->where('ele', $ele)->first())) {
                return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
            $shop->ele = $ele;
            if ($shop->second_category == 200001) {
                $shop->chufang_ele = $ele;
                $shop->chufang_status = 2;
            }
        }
        if (!is_null($jddj)) {
            $shop->jddj = $jddj;
        }

        $shop->save();

        return $this->success($shop);
    }
}
