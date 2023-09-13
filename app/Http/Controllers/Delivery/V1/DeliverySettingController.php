<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\OrderSetting;
use App\Models\Shop;
use Illuminate\Http\Request;

class DeliverySettingController extends Controller
{
    /**
     * 获取发单设置
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/9/5 10:11 上午
     */
    public function show(Request $request)
    {
        if (!$shop_id = intval($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }
        if (!$shop = Shop::select('id', 'user_id', 'mt_shop_id', 'ele_shop_id')->find($shop_id)) {
            return $this->error("门店不存在");
        }
        $user = $request->user();
        if ($shop->user_id != $user->id) {
            return $this->error("门店不存在");
        }
        if ($setting = OrderSetting::where("shop_id", $shop_id)->first()) {
            $setting = $setting->toArray();
        } else {
            $setting = config("ps.shop_setting");
        }

        $result = [
            'auto_mt' => intval(boolval($shop->mt_shop_id)),
            'auto_ele' => intval(boolval($shop->ele_shop_id)),
            'delay_send' => $setting['delay_send'],
            'delay_send_text' => $setting['delay_send'] . '秒',
            'delay_reset' => $setting['delay_reset'],
            'delay_reset_text' => $setting['delay_reset'] . '分钟',
            'type' => $setting['type'],
            'type_text' => $setting['type'] == 1 ? '是' : '否',
        ];

        return $this->success($result);
    }

    /**
     * 保存发单信息
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/9/5 10:11 上午
     */
    public function store(Request $request)
    {
        // 门店ID
        if (!$shop_id = intval($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }
        // 延时发单秒数
        $delay_send = intval($request->get('delay_send', 0));
        if ($delay_send < 10) {
            return $this->error("延时发单时间不能小于10秒");
        }
        if ($delay_send > 900) {
            return $this->error("延时发单时间不能大于900秒");
        }
        // 切换平台分钟数
        $delay_reset = intval($request->get('delay_reset', 1));
        if ($delay_reset < 1) {
            return $this->error("切换平台时间不能小于1分钟");
        }
        if ($delay_reset > 15) {
            return $this->error("切换平台时间不能大于15分钟");
        }
        // 切换发单类型
        $type = intval($request->get('type', 1));
        if (!in_array($type, [1, 2])) {
            return $this->error("切换发单类型错误");
        }
        // 判断门店是否存在
        if (!$shop = Shop::select('id', 'user_id', 'mt_shop_id', 'ele_shop_id', 'waimai_mt', 'waimai_ele')->find($shop_id)) {
            return $this->error("门店不存在");
        }
        // 获取当前操作用户
        $user = $request->user();
        // 判断门店是否当前用户创建的
        if ($shop->user_id != $user->id) {
            return $this->error("门店不存在");
        }
        // 查找是否有设置
        if (!$setting = OrderSetting::where("shop_id", $shop_id)->first()) {
            $setting = new OrderSetting;
        }
        // 设置赋值
        $setting->shop_id = $shop_id;
        $setting->delay_send = $delay_send;
        $setting->delay_reset = $delay_reset;
        $setting->type = $type;
        $setting->save();
        // 自动发单设置
        $save_status = false;
        $auto_mt = $request->get('auto_mt');
        $auto_ele = $request->get('auto_ele');
        if (!is_null($auto_mt)) {
            $auto_mt = intval($auto_mt);
            if ($auto_mt === 0 && $shop->mt_shop_id) {
                $save_status = true;
                $shop->mt_shop_id = '';
            }
            if ($auto_mt === 1 && !$shop->mt_shop_id) {
                $save_status = true;
                $shop->mt_shop_id = $shop->waimai_mt ?: $shop->id;
            }
        }
        if (!is_null($auto_ele)) {
            $auto_ele = intval($auto_ele);
            if ($auto_ele === 0 && $shop->ele_shop_id) {
                $save_status = true;
                $shop->ele_shop_id = '';
            }
            if ($auto_ele === 1 && !$shop->ele_shop_id) {
                $save_status = true;
                $shop->ele_shop_id = $shop->waimai_ele ?: $shop->id;
            }
        }
        if ($save_status) {
            $shop->save();
        }

        return $this->success();
    }
}
