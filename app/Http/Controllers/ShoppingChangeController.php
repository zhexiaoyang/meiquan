<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopAuthenticationChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShoppingChangeController extends Controller
{
    public function store(Request $request)
    {

        if (!$shop = Shop::query()->find($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }

        if (!$shop_auth = ShopAuthenticationChange::query()->where("shop_id", $shop->id)->first()) {
            $shop_auth = new ShopAuthenticationChange();
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
        $shop_auth->status = 0;
        $shop_auth->save();

        return $this->success();
    }

    public function show(Request $request)
    {
        $shop_id = $request->get("shop_id", 0);

        $user = Auth::user();

        if (!$shop = Shop::with(["auth_shop","change_shop"])->where(['id' => $shop_id, 'own_id' => $user->id])->first()) {
            return $this->error("门店不存在");
        }

        $zhizi = $shop->change_shop ?: $shop->auth_shop;

        $result['id'] = $shop->id;
        $result['reason'] = $zhizi->reason ?? null;
        $result['shop_name'] = $shop->shop_name;
        // $result['auth'] = $shop->auth;
        $result['yyzz'] = $zhizi->yyzz;
        $result['chang'] = $zhizi->chang;
        $result['yyzz_start_time'] = $zhizi->yyzz_start_time;
        $result['yyzz_end_time'] = $zhizi->yyzz_end_time;
        $result['ypjy'] = $zhizi->xkz;
        $result['ypjy_start_time'] = $zhizi->ypjy_start_time;
        $result['ypjy_end_time'] = $zhizi->ypjy_end_time;
        $result['spjy'] = $zhizi->spjy;
        $result['spjy_start_time'] = $zhizi->spjy_start_time;
        $result['spjy_end_time'] = $zhizi->spjy_end_time;
        $result['ylqx'] = $zhizi->ylqx;
        $result['ylqx_start_time'] = $zhizi->ylqx_start_time;
        $result['ylqx_end_time'] = $zhizi->ylqx_end_time;
        $result['sfz'] = $zhizi->sfz;
        $result['wts'] = $zhizi->wts;

        return $this->success($result);
    }
}
