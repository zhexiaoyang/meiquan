<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopAuthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShoppingController extends Controller
{

    /**
     * 用户认证门店列表-带筛选
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $status = $request->get("auth", 0);

        $my_shops = $user->my_shops()->where('auth', $status)->get();

        $request = [];

        if (!empty($my_shops)) {
            foreach ($my_shops as $my_shop) {
                $tmp['id'] = $my_shop->id;
                $tmp['shop_name'] = $my_shop->shop_name;
                $tmp['shop_address'] = $my_shop->shop_address;
                $request[] = $tmp;
            }
        }

        return $this->success($request);
    }

    /**
     * 提交认证门店
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }

        if (!$shop_auth = ShopAuthentication::query()->where("shop_id", $shop->id)->first()) {
            $shop_auth = new ShopAuthentication();
        }

        $chang = $request->get("chang", 0);

        if (!$yyzz = $request->get('yyzz')) {
            return $this->error("请上传营业执照");
        }
        if (!$ypjy = $request->get('ypjy')) {
            return $this->error("请上传药品经营许可证");
        }
        if (!$spjy = $request->get('spjy')) {
            return $this->error("请上传食品经营许可证");
        }
        if (!$ylqx = $request->get('ylqx')) {
            return $this->error("请上传器械证");
        }
        if (!$sfz = $request->get('sfz')) {
            return $this->error("请上传身份证");
        }
        if (!$wts = $request->get('wts')) {
            return $this->error("请上传委托书");
        }
        if (!$yyzz_start_time = $request->get('yyzz_start_time')) {
            return $this->error("请选择营业执照开始时间");
        }
        if (!$chang && (!$yyzz_end_time = $request->get('yyzz_end_time'))) {
            return $this->error("请选择营业执照结束时间");
        }
        if (!$ypjy_start_time = $request->get('ypjy_start_time')) {
            return $this->error("请选择药品经营许可证开始时间");
        }
        if (!$ypjy_end_time = $request->get('ypjy_end_time')) {
            return $this->error("请选择药品经营许可证结束时间");
        }
        if (!$spjy_start_time = $request->get('spjy_start_time')) {
            return $this->error("请选择食品经营许可证开始时间");
        }
        if (!$spjy_end_time = $request->get('spjy_end_time')) {
            return $this->error("请选择食品经营许可证结束时间");
        }
        if (!$ylqx_start_time = $request->get('ylqx_start_time')) {
            return $this->error("请选择器械证开始时间");
        }
        if (!$ylqx_end_time = $request->get('ylqx_end_time')) {
            return $this->error("请选择器械证结束时间");
        }

        $shop_auth->shop_id = $shop->id;
        $shop_auth->chang = $chang;
        $shop_auth->yyzz = $yyzz;
        $shop_auth->xkz = $ypjy;
        $shop_auth->spjy = $spjy;
        $shop_auth->ylqx = $ylqx;
        $shop_auth->sfz = $sfz;
        $shop_auth->wts = $wts;
        $shop_auth->yyzz_start_time = $yyzz_start_time;
        $shop_auth->yyzz_end_time = $yyzz_end_time ?? null;
        $shop_auth->ypjy_start_time = $ypjy_start_time;
        $shop_auth->ypjy_end_time = $ypjy_end_time;
        $shop_auth->spjy_start_time = $spjy_start_time;
        $shop_auth->spjy_end_time = $spjy_end_time;
        $shop_auth->ylqx_start_time = $ylqx_start_time;
        $shop_auth->ylqx_end_time = $ylqx_end_time;


        if ($shop_auth->save()) {
            $shop->auth = 1;
            $shop->apply_auth_time = date("Y-m-d H:i:s");
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 认证门店更新
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function update(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }

        if (!$shop_auth = ShopAuthentication::query()->where("shop_id", $shop->id)->first()) {
            $shop_auth = new ShopAuthentication();
        }

        $chang = $request->get("chang", 0);

        if (!$yyzz = $request->get('yyzz')) {
            return $this->error("请上传营业执照");
        }
        if (!$ypjy = $request->get('ypjy')) {
            return $this->error("请上传药品经营许可证");
        }
        if (!$spjy = $request->get('spjy')) {
            return $this->error("请上传食品经营许可证");
        }
        if (!$ylqx = $request->get('ylqx')) {
            return $this->error("请上传器械证");
        }
        if (!$sfz = $request->get('sfz')) {
            return $this->error("请上传身份证");
        }
        if (!$wts = $request->get('wts')) {
            return $this->error("请上传委托书");
        }
        if (!$yyzz_start_time = $request->get('yyzz_start_time')) {
            return $this->error("请选择营业执照开始时间");
        }
        if (!$chang && (!$yyzz_end_time = $request->get('yyzz_end_time'))) {
            return $this->error("请选择营业执照结束时间");
        }
        if (!$ypjy_start_time = $request->get('ypjy_start_time')) {
            return $this->error("请选择药品经营许可证开始时间");
        }
        if (!$ypjy_end_time = $request->get('ypjy_end_time')) {
            return $this->error("请选择药品经营许可证结束时间");
        }
        if (!$spjy_start_time = $request->get('spjy_start_time')) {
            return $this->error("请选择食品经营许可证开始时间");
        }
        if (!$spjy_end_time = $request->get('spjy_end_time')) {
            return $this->error("请选择食品经营许可证结束时间");
        }
        if (!$ylqx_start_time = $request->get('ylqx_start_time')) {
            return $this->error("请选择器械证开始时间");
        }
        if (!$ylqx_end_time = $request->get('ylqx_end_time')) {
            return $this->error("请选择器械证结束时间");
        }

        $shop_auth->shop_id = $shop->id;
        $shop_auth->chang = $chang;
        $shop_auth->yyzz = $yyzz;
        $shop_auth->xkz = $ypjy;
        $shop_auth->spjy = $spjy;
        $shop_auth->ylqx = $ylqx;
        $shop_auth->sfz = $sfz;
        $shop_auth->wts = $wts;
        $shop_auth->yyzz_start_time = $yyzz_start_time;
        $shop_auth->yyzz_end_time = $yyzz_end_time ?? null;
        $shop_auth->ypjy_start_time = $ypjy_start_time;
        $shop_auth->ypjy_end_time = $ypjy_end_time;
        $shop_auth->spjy_start_time = $spjy_start_time;
        $shop_auth->spjy_end_time = $spjy_end_time;
        $shop_auth->ylqx_start_time = $ylqx_start_time;
        $shop_auth->ylqx_end_time = $ylqx_end_time;


        if ($shop_auth->save()) {
            $shop->auth = 1;
            $shop->apply_auth_time = date("Y-m-d H:i:s");
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 提交认证列表
     * @param Request $request
     * @return mixed
     */
    public function shopAuthList(Request $request)
    {
        $search_key = $request->get('search_key', '');

        $shops = [];

        $user = Auth::user();

        $query = Shop::with(["auth_shop", "change_shop"])->where("own_id", $user->id)->where("auth", ">", 0);

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
                    $tmp['change_status'] = $v->change_shop->status ?? -1;
                    $tmp['examine_at'] = $v->auth_shop->examine_at ? date("Y-m-d H:i:s", strtotime($v->auth_shop->examine_at)) : "";
                    $tmp['created_at'] = date("Y-m-d H:i:s", strtotime($v->auth_shop->created_at));
                    $shops[] = $tmp;
                }
            }
        }

        return $this->success($shops);
    }

    /**
     * 商城认证门店详情
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function show(Request $request)
    {
        $shop_id = $request->get("shop_id", 0);

        $user = Auth::user();

        if (!$shop = Shop::with("auth_shop")->where(['id' => $shop_id, 'own_id' => $user->id])->first()) {
            return $this->error("门店不存在");
        }

        $result['id'] = $shop->id;
        $result['reason'] = $shop->auth_error;
        $result['shop_name'] = $shop->shop_name;
        $result['auth'] = $shop->auth;
        $result['yyzz'] = $shop->auth_shop->yyzz;
        $result['chang'] = $shop->auth_shop->chang;
        $result['yyzz_start_time'] = $shop->auth_shop->yyzz_start_time;
        $result['yyzz_end_time'] = $shop->auth_shop->yyzz_end_time;
        $result['ypjy'] = $shop->auth_shop->xkz;
        $result['ypjy_start_time'] = $shop->auth_shop->ypjy_start_time;
        $result['ypjy_end_time'] = $shop->auth_shop->ypjy_end_time;
        $result['spjy'] = $shop->auth_shop->spjy;
        $result['spjy_start_time'] = $shop->auth_shop->spjy_start_time;
        $result['spjy_end_time'] = $shop->auth_shop->spjy_end_time;
        $result['ylqx'] = $shop->auth_shop->ylqx;
        $result['ylqx_start_time'] = $shop->auth_shop->ylqx_start_time;
        $result['ylqx_end_time'] = $shop->auth_shop->ylqx_end_time;
        $result['sfz'] = $shop->auth_shop->sfz;
        $result['wts'] = $shop->auth_shop->wts;
        $result['examine_at'] = $shop->auth_shop->examine_at ? date("Y-m-d H:i:s", strtotime($shop->auth_shop->examine_at)) : "";
        $result['created_at'] = date("Y-m-d H:i:s", strtotime($shop->auth_shop->created_at));

        return $this->success($result);
    }

    /**
     * 认证成功的门店列表
     * @return mixed
     * @author zhangzhen
     * @data 2020/11/4 12:52 上午
     */
    public function shopAuthSuccessList(Request $request)
    {
        $receive_shop_id = $request->user()->receive_shop_id;

        $user = Auth::user();

        $shops = Shop::query()->select("id", "shop_name", "shop_address")->where("own_id", $user->id)
            ->where("auth", 10)->get();

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if ($shop->id === $receive_shop_id) {
                    $shop->is_select = 1;
                } else {
                    $shop->is_select = 0;
                }
            }
        }

        return $this->success($shops);
    }
}
