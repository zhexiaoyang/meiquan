<?php

namespace App\Http\Controllers;

use App\Models\OrderSetting;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderSettingController extends Controller
{
    public function show(Request $request)
    {
        if (!$shop_id = intval($request->get("id"))) {
            return $this->error("门店不存在");
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error("门店不存在");
        }
        if ($setting = OrderSetting::query()->where("shop_id", $shop_id)->first()) {
            $setting->tool = $shop->tool;
        } else {
            $setting = config("ps.shop_setting");
        }

        return $this->success($setting);
    }

    public function store(Request $request)
    {
        if (!$shop = Shop::query()->where(['own_id' => Auth::id(), 'id' => intval($request->get("id"))])->first()) {
            return $this->error('门店不存在');
        }

        if (!$setting = OrderSetting::query()->where("shop_id", intval($request->get("id")))->first()) {
            $setting = new OrderSetting;
        }

        $setting->shop_id =(int) $request->get("id");

        $delay_send = intval($request->get('delay_send', 0));
        if ($delay_send < 0 || $delay_send > 300) {
            return $this->error("延时发单时间不正确");
        }
        $setting->delay_send = $delay_send;

        $delay_reset = intval($request->get('delay_reset', 1));
        if ($delay_reset < 1 || $delay_reset > 15) {
            return $this->error("切换平台时间不正确");
        }
        $setting->delay_reset = $delay_reset;

        $type = intval($request->get('type', 1));
        if (!in_array($type, [1, 2])) {
            return $this->error("状态错误");
        }
        $setting->type = $type;

        $setting->meituan = intval($request->get("meituan")) ? 1 : 0;
        $setting->fengniao = intval($request->get("fengniao")) ? 1 : 0;
        $setting->shansong = intval($request->get("shansong")) ? 1 : 0;
        $setting->meiquanda = intval($request->get("meiquanda")) ? 1 : 0;
        $setting->dada = intval($request->get("dada")) ? 1 : 0;
        $setting->uu = intval($request->get("uu")) ? 1 : 0;
        $setting->shunfeng = intval($request->get("shunfeng")) ? 1 : 0;

        $warehouse = $request->get('warehouse');
        if ($warehouse) {
            if (($stime = $request->get('stime')) && ($etime = $request->get('etime'))) {
                $setting->warehouse = $warehouse;
                $setting->warehouse_time = $stime . '-' . $etime;
            }
        }
        if ($warehouse == 0) {
            $setting->warehouse = 0;
            $setting->warehouse_time = '';
        }

        $setting->save();

        $tool = intval($request->get("tool"));
        if ($tool != $shop->tool) {
            $shop->tool = $tool;
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 恢复默认设置
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/22 10:48 下午
     */
    public function reset(Request $request)
    {
        if (!$shop = Shop::query()->where(['own_id' => Auth::id(), 'id' => intval($request->get("id"))])->first()) {
            return $this->error('门店不存在');
        }
        OrderSetting::where("shop_id", intval($request->get("id")))->delete();
        if ($shop->tool === 8) {
            $shop->tool = 0;
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 用户所创建的门店列表
     * @author zhangzhen
     * @data 2021/5/3 10:06 下午
     */
    public function shops(Request $request)
    {
        $name = $request->get('name');
        if ($request->user()->hasRole('super_man')) {
            if ($name) {
                $shops = Shop::select("id", "shop_name as name")->where('shop_name', 'like', "%{$name}%")->get();
            } else {
                $shops = [];
            }
        } else {
            if ($name) {
                $shops = Shop::select("id", "shop_name as name")->where('shop_name', 'like', "%{$name}%")->where("own_id", Auth::id())->get();
            } else {
                $shops = Shop::select("id", "shop_name as name")->where("own_id", Auth::id())->get();
            }
        }

        return $this->success($shops);
    }

    public function warehouse_shops(Request $request)
    {
        $name = $request->get('name');
        if ($request->user()->hasRole('super_man')) {
            if ($name) {
                $shops = Shop::select("id", "shop_name as name")->where('shop_name', 'like', "%{$name}%")->get();
            } else {
                $shops = [];
            }
        } else {
            if ($name) {
                $shops = Shop::select("id", "shop_name as name")->where('shop_name', 'like', "%{$name}%")->where("own_id", Auth::id())->get();
            } else {
                $shops = Shop::select("id", "shop_name as name")->where("own_id", Auth::id())->get();
            }
        }

        return $this->success($shops);
    }
}
