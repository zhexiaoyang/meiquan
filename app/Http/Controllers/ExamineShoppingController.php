<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopAuthentication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamineShoppingController extends Controller
{

    /**
     * 管理员审核门店-列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $search_key = $request->get('search_key', '');

        $shops = [];

        $query = Shop::with("auth_shop")->where("auth", 1);

        if ($search_key) {
            $query->where("shop_name", "like", "{$search_key}");
        }

        $data = $query->get();

        if (!empty($data)) {
            foreach ($data as $v) {
                if ($v->auth_shop) {
                    $tmp['id'] = $v->id;
                    $tmp['shop_name'] = $v->shop_name;
                    $tmp['auth'] = $v->auth;
                    $tmp['yyzz'] = $v->auth_shop->yyzz;
                    $tmp['xkz'] = $v->auth_shop->xkz;
                    $tmp['sfz'] = $v->auth_shop->sfz;
                    $tmp['wts'] = $v->auth_shop->wts;
                    $tmp['examine_at'] = $v->auth_shop->examine_at ? date("Y-m-d H:i:s", strtotime($v->auth_shop->examine_at)) : "";
                    $tmp['created_at'] = date("Y-m-d H:i:s", strtotime($v->auth_shop->created_at));
                    $shops[] = $tmp;
                }
            }
        }

        return $this->success($shops);
    }

    /**
     * 管理员审核门店
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $shop_id = $request->get("shop_id", 0);
        $status = $request->get("status", 0);
        $reason = $request->get("reason", "");

        if (!$shop_id) {
            return $this->error("门店不存在");
        }

        if (!in_array($status, [3,10])) {
            return $this->error("状态错误");
        }

        if ($status === 3) {
            if (!$reason) {
                return $this->error("原因不能为空");
            }
        } else {
            $reason = "";
        }

        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error("门店不存在");
        }

        if (!$shop_auth = ShopAuthentication::query()->where("shop_id", $shop->id)->first()) {
            return $this->error("门店未提交资质");
        }

        \DB::beginTransaction();
        try {

            $shop->auth = $status;
            $shop->auth_error = $reason;
            $shop->adopt_material_time = date("Y-m-d H:i:s");
            $shop->save();

            $shop_auth->update(['examine_user_id' => Auth::user()->id, 'examine_at' => date("Y-m-d H:i:s")]);

            $user = User::find($shop->own_id);

            if ($user && !$user->can("supplier")) {
                $user->givePermissionTo("supplier");
            }

            $user->assignRole('shop');
            \DB::commit();
        }
        catch(\Exception $ex) {
            \DB::rollback();
            return $this->error("审核失败");
        }

        return $this->success();
    }
}
