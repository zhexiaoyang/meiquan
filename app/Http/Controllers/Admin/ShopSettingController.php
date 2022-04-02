<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderSetting;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopSettingController extends Controller
{
    public function show(Request $request)
    {
        $shop_id = $request->get("id", 0);

        if (!$shop = Shop::find($shop_id)) {
            return $this->error("门店不存在", 422);
        }
        if ($setting = OrderSetting::where("shop_id", $shop_id)->first()) {
            $setting->tool = $shop->tool;
            $setting = $setting->toArray();
        } else {
            $setting = config("ps.shop_setting");
        }

        // $setting['meituan'] || $platform[] = 'mt';
        // $setting['fengniao'] || $platform[] = 'fn';
        // $setting['shansong'] || $platform[] = 'ss';
        // $setting['meiquanda'] || $platform[] = 'mqd';
        // $setting['dada'] || $platform[] = 'dd';
        // $setting['uu'] || $platform[] = 'uu';
        // $setting['shunfeng'] || $platform[] = 'sf';
        $platform = [];
        $shop_platform = [];
        if ($shop->shop_id) {
            $shop_platform[] = 'mt';
            if ($setting['meituan']) {
                $platform[] = 'mt';
            }
        }
        if ($shop->shop_id_fn) {
            $shop_platform[] = 'fn';
            if ($setting['fengniao']) {
                $platform[] = 'fn';
            }
        }
        if ($shop->shop_id_ss) {
            $shop_platform[] = 'ss';
            if ($setting['shansong']) {
                $platform[] = 'ss';
            }
        }
        if ($shop->shop_id_mqd) {
            $shop_platform[] = 'mqd';
            if ($setting['meiquanda']) {
                $platform[] = 'mqd';
            }
        }
        if ($shop->shop_id_uu) {
            $shop_platform[] = 'uu';
            if ($setting['uu']) {
                $platform[] = 'uu';
            }
        }
        if ($shop->shop_id_sf) {
            $shop_platform[] = 'sf';
            if ($setting['shunfeng']) {
                $platform[] = 'sf';
            }
        }
        if ($shop->shop_id_dd) {
            $shop_platform[] = 'dd';
            if ($setting['dada']) {
                $platform[] = 'dd';
            }
        }

        $res = [
            'id' => $shop_id,
            'call' => $setting['call'],
            'delay_reset' => $setting['delay_reset'],
            'delay_send' => $setting['delay_send'],
            'platform' => $platform,
            'shop_platform' => $shop_platform,
            'tool' => $setting['tool'],
            'type' => $setting['type'],
        ];

        return $this->success($res);
    }

    public function update(Request $request)
    {
        $shop_id = $request->get("id", 0);

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        if (!$setting = OrderSetting::where("shop_id", $shop_id)->first()) {
            $setting = new OrderSetting;
        }

        $setting->shop_id = $shop_id;

        // 呼叫模式
        $call = intval($request->get('call', 1));
        if (!in_array($call, [1, 2])) {
            return $this->error("呼叫模式错误");
        }
        $setting->call = $call;
        // 延时发单
        $delay_send = intval($request->get('delay_send', 0));
        if ($delay_send < 0 || $delay_send > 300) {
            return $this->error("延时发单时间不正确");
        }
        $setting->delay_send = $delay_send;
        // 切换平台时间
        $delay_reset = intval($request->get('delay_reset', 1));
        if ($delay_reset < 1 || $delay_reset > 15) {
            return $this->error("切换平台时间不正确");
        }
        $setting->delay_reset = $delay_reset;
        // 继续发单时是否取消之前发单
        $type = intval($request->get('type', 1));
        if (!in_array($type, [1, 2])) {
            return $this->error("状态错误");
        }
        $setting->type = $type;
        // 发单平台
        $platform = $request->get('platform', []);
        if (!is_array($platform)) {
            return $this->error("平台参数错误");
        }
        if ($shop->shop_id) {
            if (in_array('mt', $platform)) {
                $setting->meituan = 1;
            } else {
                $setting->meituan = 0;
            }
        }
        if ($shop->shop_id_fn) {
            if (in_array('fn', $platform)) {
                $setting->fengniao = 1;
            } else {
                $setting->fengniao = 0;
            }
        }
        if ($shop->shop_id_ss) {
            if (in_array('ss', $platform)) {
                $setting->shansong = 1;
            } else {
                $setting->shansong = 0;
            }
        }
        if ($shop->shop_id_mqd) {
            if (in_array('mqd', $platform)) {
                $setting->meiquanda = 1;
            } else {
                $setting->meiquanda = 0;
            }
        }
        if ($shop->shop_id_dd) {
            if (in_array('dd', $platform)) {
                $setting->dada = 1;
            } else {
                $setting->dada = 0;
            }
        }
        if ($shop->shop_id_uu) {
            if (in_array('uu', $platform)) {
                $setting->uu = 1;
            } else {
                $setting->uu = 0;
            }
        }
        if ($shop->shop_id_sf) {
            if (in_array('sf', $platform)) {
                $setting->shunfeng = 1;
            } else {
                $setting->shunfeng = 0;
            }
        }
        $setting->save();
        // 交通工具
        $tool = intval($request->get("tool"));
        if ($tool != $shop->tool) {
            $shop->tool = $tool;
            $shop->save();
        }

        return $this->success();
    }
}
