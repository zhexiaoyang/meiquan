<?php

namespace App\Http\Controllers;

use App\Models\OrderSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderSettingController extends Controller
{
    public function show()
    {
        if (!$setting = OrderSetting::query()->where("user_id", Auth::id())->first()) {
            $setting = config("ps.user_setting");
        }

        return $this->success($setting);
    }

    public function store(Request $request)
    {
        if (!$setting = OrderSetting::query()->where("user_id", Auth::id())->first()) {
            $setting = new OrderSetting;
        }

        $setting->user_id = Auth::id();

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
        $setting->shunfeng = intval($request->get("shunfeng")) ? 1 : 0;
        $setting->dada = intval($request->get("dada")) ? 1 : 0;

        $setting->save();

        return $this->success();
    }

    /**
     * 恢复默认设置
     * @return mixed
     * @author zhangzhen
     * @data 2021/4/22 10:48 下午
     */
    public function reset()
    {
        OrderSetting::where("user_id", Auth::id())->delete();

        return $this->success();
    }
}
