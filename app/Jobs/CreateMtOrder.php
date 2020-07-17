<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateMtOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $dingding;
    protected $services = [];
    protected $base = 0;
    protected $weight_money = 0;
    protected $time_money = 0;
    protected $money_ss = 16;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->dingding = app("ding");
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $this->dingTalk("发送订单", "开始");
        $shop = Shop::query()->find($this->order->shop_id);
        $user = User::query()->find($shop->user_id ?? 0);

        // 判断用户和门店是否存在
        if (!$shop && !$user) {
            Log::info('发送订单-用户和门店不存在', ['id' => $this->order->id, 'order_id' => $this->order->order_id]);
        }

        // 判断用户金额是否满足最小订单
        if ($user->money <= 5.2) {
            $this->order->status = 5;
            $this->order->save();
            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 5]));
            Log::info('发送订单-用户金额不足5.2元', ['id' => $this->order->id, 'order_id' => $this->order->order_id]);
            return;
        }

        $money_mt = 0;
        $money_fn = 0;
        $money_ss = 0;
        $ss_order_id = "";

        // 判断美团是否可以接单、并加入数组
        if ($shop->shop_id && !$this->order->fail_mt) {
            $meituan = app("meituan");
            $check_mt = $meituan->check($shop, $this->order);
            if (isset($check_mt['code']) && ($check_mt['code'] === 0)) {
                $money_mt = distanceMoney($this->order->distance) + baseMoney($shop->city_level ?: 9) + timeMoney() + dateMoney() + weightMoney($this->order->goods_weight);
                $this->services['meituan'] = $money_mt;
                Log::info('发送订单-美团可以', ['money' => $money_mt, 'id' => $this->order->id, 'order_id' => $this->order->order_id]);
            } else {
                $this->order->fail_mt = $check_mt['message'] ?? "美团校验订单请求失败";
                $this->order->save();
            }
        }

        // 判断蜂鸟是否可以接单、并加入数组
        if ($shop->shop_id_fn && !$this->order->fail_fn) {


            if ((time() > strtotime(date("Y-m-d 22:00:00"))) || (time() < strtotime(date("Y-m-d 09:00:00")))) {
                \Log::info('禁止发送蜂鸟', ["time" => time(), "date" => date("Y-m-d H:i:s")]);
            } else {
                $fengniao = app("fengniao");
                $check_fn = $fengniao->delivery($shop, $this->order);
                if (isset($check_fn['code']) && ($check_fn['code'] == 200)) {
                    $money_fn = distanceMoneyFn($this->order->distance) + baseMoneyFn($shop->city_level_fn ?: "G") + timeMoneyFn() + weightMoneyFn($this->order->goods_weight);
                    $this->services['fengniao'] = $money_fn;
                    Log::info('发送订单-蜂鸟可以', ['money' => $money_fn, 'id' => $this->order->id, 'order_id' => $this->order->order_id]);
                } else {
                    $this->order->fail_fn = $check_fn['msg'] ?? "蜂鸟校验请求失败";
                    $this->order->save();
                }
            }
        }

        // 判断闪送是否可以接单、并加入数组
        if ($shop->shop_id_ss && !$this->order->fail_ss) {
            $shansong = app("shansong");
            $check_ss = $shansong->orderCalculate($shop, $this->order);
            if (isset($check_ss['status']) && ($check_ss['status'] === 200) ) {

                if (isset($check_ss['data']['feeInfoList']) && !empty($check_ss['data']['feeInfoList'])) {
                    foreach ($check_ss['data']['feeInfoList'] as $v) {
                        if ($v['type'] == 1) {
                            $this->base = $v['fee'] / 100 ?? 0;
                        }
                        if ($v['type'] == 2) {
                            $this->weight_money = $v['fee'] / 100 ?? 0;
                        }
                        if ($v['type'] == 7) {
                            $this->time_money = $v['fee'] / 100 ?? 0;
                        }
                    }

                    $money_ss = ($check_ss['data']['totalAmount'] ?? 0) / 100;
                    if ($money_ss <= 26) {
                        $money_ss = $money_ss * 0.8;
                    } else {
                        $money_ss = (26 * 0.8) + ($money_ss - 26);
                    }
                    $this->money_ss = $money_ss;
                    $ss_order_id = $check_ss['data']['orderNumber'] ?? 0;
                    $this->services['shansong'] = $money_ss;
                    Log::info('发送订单-闪送可以', ['money' => $money_ss, 'id' => $this->order->id, 'order_id' => $this->order->order_id]);
                }
            } else {
                $this->order->fail_ss = $check_ss['msg'] ?? "闪送校验订单请求失败";
                $this->order->save();
            }
        }

        // 没有配送服务商
        if (empty($this->services)) {
            $this->order->status = 10;
            $this->order->save();
            $this->dingTalk("发送订单失败", "暂无运力");
            return;
        }

        // 更新价格
        DB::table('orders')->where('id', $this->order->id)->update([
            "money_mt" => $money_mt,
            "money_fn" => $money_fn,
            "money_ss" => $money_ss,
            "ss_order_id" => $ss_order_id
        ]);

        $ps = $this->getService();

        if ($ps === "meituan") {
            if ($this->meituan()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, config("ps.order_ttl")));
                }
            } else {
                $this->dingTalk("发送订单失败", "发送订单失败");
            }
            return;
        } else if ($ps === "fengniao") {
            if ($this->fengniao()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, config("ps.order_ttl")));
                }
            } else {
                $this->dingTalk("发送订单失败", "发送订单失败");
            }
            return;
        } else if ($ps === "shansong") {
            if ($this->shansong()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, config("ps.order_ttl")));
                }
            } else {
                $this->dingTalk("发送订单失败", "发送订单失败");
            }
            return;
        }
        $this->dingTalk("发送订单失败", "没有返回最小平台");
    }

    public function meituan()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->ps) {
            $this->dingTalk("不能发送美团订单", "ps状态错误");
            return false;
        }

        if ($order->fail_mt) {
            $this->dingTalk("不能发送美团订单", "已有美团错误信息");
            return false;
        }
        $shop = Shop::query()->find($this->order->shop_id);
        $user = User::query()->find($shop->user_id ?? 0);

        // 判断用户和门店是否存在
        if (!$shop && !$user) {
            $this->dingTalk("不能发送美团订单", "门店或用户不存在");
            return false;
        }

        $meituan = app("meituan");
        $distance = distanceMoney($this->order->distance);
        $base = baseMoney($shop->city_level ?: 9);
        $time_money = timeMoney();
        $date_money = dateMoney();
        $weight_money = weightMoney($this->order->goods_weight);

        $money = $base + $time_money + $date_money + $distance + $weight_money;

        if ($money >= 0 && ($user->money > $money) && DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money - $money])) {
            Log::info('美团订单-扣款成功', ['order_id' => $this->order->id, 'user_id' => $user->id, 'money' => $money]);
            // 发送美团订单
            $result_mt = $meituan->createByShop($shop, $this->order);
            if ($result_mt['code'] === 0) {
                // 订单发送成功
                $this->dingTalk("美团成功", "发送美团订单成功");
                // 写入订单信息
                $update_info = [
                    'money' => $money,
                    'base_money' => $base,
                    'distance_money' => $distance,
                    'weight_money' => $weight_money,
                    'time_money' => $time_money,
                    'date_money' => $date_money,
                    'peisong_id' => $result_mt['data']['mt_peisong_id'],
                    'status' => 20,
                    'ps' => 1
                ];
                DB::table('orders')->where('id', $this->order->id)->update($update_info);
                Log::info('美团订单-更新创建订单状态成功');
                return true;
            } else {
                $fail_mt = $result_mt['message'] ?? "美团创建订单失败";
                DB::table('orders')->where('id', $this->order->id)->update(['fail_mt' => $fail_mt]);
                DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money + $money]);
                Log::info('美团发送创建失败-把钱返给用户', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                if (count($this->services) > 1) {
                    dispatch(new CreateMtOrder($this->order));
                } else {
                    $this->dingTalk("不能发送订单", "没有平台了");
                }
                $this->dingTalk("不能发送美团订单", "美团创建订单失败了");
            }
        } else {
            Log::info('美团订单-余额不足-扣款失败', ['order_id' => $this->order->id, 'user_id' => $user->id]);
            $this->order->status = 5;
            $this->order->save();
            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
            $this->dingTalk("不能发送美团订单", "余额不足");
        }

        return false;
    }

    public function fengniao()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->ps) {
            $this->dingTalk("不能发送蜂鸟订单", "ps状态错误");
            return false;
        }

        if ($order->fail_fn) {
            $this->dingTalk("不能发送蜂鸟订单", "已有蜂鸟错误信息");
            return false;
        }
        $shop = Shop::query()->find($this->order->shop_id);
        $user = User::query()->find($shop->user_id ?? 0);

        // 判断用户和门店是否存在
        if (!$shop && !$user) {
            $this->dingTalk("不能发送蜂鸟订单", "门店或用户不存在");
            return false;
        }

        $fengniao = app("fengniao");
        $distance = distanceMoneyFn($this->order->distance);
        $base = baseMoneyFn($shop->city_level_fn ?: "G");
        $time_money = timeMoneyFn();
        $date_money = 0;
        $weight_money = weightMoneyFn($this->order->goods_weight);

        $money = $base + $time_money + $date_money + $distance + $weight_money;

        if ($money >= 0 && ($user->money > $money) && DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money - $money])) {
            Log::info('蜂鸟订单-扣款成功', ['order_id' => $this->order->id, 'user_id' => $user->id, 'money' => $money]);
            $send = true;
            $result_fn = $fengniao->createOrder($shop, $this->order);
            if ($result_fn['code'] == 200) {
                // 订单发送成功
                $this->dingTalk("蜂鸟成功", "发送蜂鸟订单成功");
                // 写入订单信息
                $update_info = [
                    'money' => $money,
                    'base_money' => $base,
                    'distance_money' => $distance,
                    'weight_money' => $weight_money,
                    'time_money' => $time_money,
                    'date_money' => $date_money,
                    'peisong_id' => $result_fn['data']['peisong_id'] ?? $this->order->order_id,
                    'status' => 20,
                    'ps' => 2
                ];
                DB::table('orders')->where('id', $this->order->id)->update($update_info);
                Log::info('蜂鸟订单-更新创建订单状态成功');
                return true;
            } else {
                $fail_fn = $result_fn['msg'] ?? "蜂鸟创建订单失败";
                DB::table('orders')->where('id', $this->order->id)->update(['fail_fn' => $fail_fn]);
                DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money + $money]);
                Log::info('蜂鸟发送创建失败-把钱返给用户', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                if (count($this->services) > 1) {
                    dispatch(new CreateMtOrder($this->order));
                } else {
                    $this->dingTalk("不能发送订单", "没有平台了");
                }
                $this->dingTalk("不能发送蜂鸟订单", "蜂鸟创建订单失败了");
            }
        } else {
            Log::info('蜂鸟订单-余额不足-扣款失败', ['order_id' => $this->order->id, 'user_id' => $user->id]);
            $this->order->status = 5;
            $this->order->save();
            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
            $this->dingTalk("不能发送蜂鸟订单", "余额不足");
        }

        return false;
    }

    public function shansong()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->ps) {
            $this->dingTalk("不能发送闪送订单", "ps状态错误");
            return false;
        }

        if ($order->fail_ss) {
            $this->dingTalk("不能发送闪送订单", "已有闪送错误信息");
            return false;
        }
        $shop = Shop::query()->find($this->order->shop_id);
        $user = User::query()->find($shop->user_id ?? 0);

        // 判断用户和门店是否存在
        if (!$shop && !$user) {
            $this->dingTalk("不能发送闪送订单", "门店或用户不存在");
            return false;
        }

        $shansong = app("shansong");
        $distance = 0;
        $base = $this->base;
        $time_money = $this->time_money;
        $date_money = 0;
        $weight_money = $this->weight_money;

        $money = $this->money_ss;

        if ($money >= 0 && ($user->money > $money) && DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money - $money])) {
            Log::info('闪送订单-扣款成功', ['order_id' => $this->order->id, 'user_id' => $user->id, 'money' => $money]);
            // 发送闪送订单
            $result_ss = $shansong->createOrder($order->ss_order_id);
            if ($result_ss['status'] === 200) {
                // 订单发送成功
                $this->dingTalk("闪送成功", "发送闪送订单成功");
                // 写入订单信息
                $update_info = [
                    'money' => $money,
                    'base_money' => $base,
                    'distance_money' => $distance,
                    'weight_money' => $weight_money,
                    'time_money' => $time_money,
                    'date_money' => $date_money,
                    'peisong_id' => $order->ss_order_id,
                    'status' => 20,
                    'ps' => 3
                ];
                DB::table('orders')->where('id', $this->order->id)->update($update_info);
                Log::info('闪送订单-更新创建订单状态成功');
                return true;
            } else {
                $fail_ss = $result_ss['msg'] ?? "闪送创建订单失败";
                DB::table('orders')->where('id', $this->order->id)->update(['fail_ss' => $fail_ss]);
                DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money + $money]);
                Log::info('闪送发送创建失败-把钱返给用户', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                if (count($this->services) > 1) {
                    dispatch(new CreateMtOrder($this->order));
                } else {
                    $this->dingTalk("不能发送订单", "没有平台了");
                }
                $this->dingTalk("不能发送闪送订单", "闪送创建订单失败了");
            }
        } else {
            Log::info('闪送订单-余额不足-扣款失败', ['order_id' => $this->order->id, 'user_id' => $user->id]);
            $this->order->status = 5;
            $this->order->save();
            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
            $this->dingTalk("不能发送闪送订单", "余额不足");
        }

        return false;
    }

    /**
     * 发送钉钉异常
     * @param $title
     * @param $description
     */
    public function dingTalk($title, $description)
    {
        $logs = [
            "des" => $description,
            "datetime" => date("Y-m-d H:i:s"),
            "order_id" => $this->order->order_id,
            "id" => $this->order->id
        ];
        $res = $this->dingding->sendMarkdownMsgArray($title, $logs);
        \Log::error('发送订单-'.$title, ["logs" => $logs, "dingding" => $res]);
    }

    /**
     * 配送商价格排序
     * @return array
     */
    public function getService()
    {
        $min = 0;
        $res = "";
        $services = $this->services;
        if (count($services) === 1) {
            return array_keys($services)[0];
        }

        foreach ($services as $k => $v) {
            if ($min === 0) {
                $min = $v;
                $res = $k;
            }

            if ($v < $min) {
                $min = $v;
                $res = $k;
            }
        }

        return $res;
    }
}
