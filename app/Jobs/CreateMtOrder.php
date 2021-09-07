<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
    protected $money_dd = 8;
    protected $money_uu = 8;
    protected $log = "";
    protected $dada_order_id = '';
    protected $uu_order_id = '';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order, $ttl = 0)
    {
        $this->delay = $ttl;
        $this->order = $order;
        $this->dingding = app("ding");
        $this->log = "[发单|id:{$order->id},order_id:{$order->order_id}]-";
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ((strtotime($this->order->created_at) + 86400 * 2) < time()) {
            Log::info($this->log."订单创建时间超出两天,不能发单，time:" . $this->order->created_at);
            return;
        }

        if ($this->order->status > 30) {
            Log::info($this->log."订单状态不正确,不能发单");
            return;
        }

        if (!Redis::setnx("send_order_id_" . $this->order->order_id, $this->order->order_id)) {
            \Log::info("[CreateMtOrder]-[发送跑腿订单]-[订单ID: {$this->order->order_id}]]-重复发送");
            return;
        }
        Redis::expire("send_order_id_" . $this->order->order_id, 6);

        // \Log::info("(CreateMtOrder)订单信息", [$this->order->status]);
        // return;

        // $this->dingTalk("发送订单", "开始");
        Log::info($this->log."开始");

        // 相关信息
        $shop = Shop::query()->find($this->order->shop_id);
        $user = User::query()->find($shop->user_id ?? 0);
        $setting = OrderSetting::where("shop_id", $shop->id)->first();
        if ($setting) {
            $order_ttl = $setting->delay_reset * 60;
            $mt_switch = $setting->meituan;
            $fn_switch = $setting->fengniao;
            $ss_switch = $setting->shansong;
            $mqd_switch = $setting->meiquanda;
            $dd_switch = $setting->dada;
            $uu_switch = $setting->uu;
        } else {
            $order_ttl = config("ps.shop_setting.delay_reset") * 60;
            $mt_switch = config("ps.shop_setting.meituan");
            $fn_switch = config("ps.shop_setting.fengniao");
            $ss_switch = config("ps.shop_setting.shansong");
            $mqd_switch = config("ps.shop_setting.meiquanda");
            $uu_switch = config("ps.shop_setting.uu");
            $dd_switch = config("ps.shop_setting.dd");
        }

        Log::info($this->log."检查重新发送时间：{{ $order_ttl }} 秒");

        if ($order_ttl < 60) {
            $order_ttl = 60;
            Log::info($this->log."检查重新发送时间小于60秒，重置为60秒");
        }

        // 判断用户和门店是否存在
        if (!$shop && !$user) {
            Log::info($this->log."用户和门店不存在,不能发单");
            return;
        }

        // 判断用户金额是否满足最小订单
        if ($user->money <= 5.2) {
            if ($this->order->status < 20) {
                DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
            }
            // DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 5]));
            Log::info($this->log."用户金额不足5.2元,不能发单");
            // Log::info('发送订单-用户金额不足5.2元', ['id' => $this->order->id, 'order_id' => $this->order->order_id]);
            return;
        }

        $use_money = 0;
        $orders = DB::table("orders")->where("user_id", $user->id)->whereIn("status", [20, 30])->get();
        if ($orders->isNotEmpty()) {
            foreach ($orders as $v) {
                if ($v->id !== $this->order->id) {
                    $use_money += max($v->money_mt, $v->money_fn, $v->money_ss, $v->money_dd, $v->money_mqd);
                }
            }
        }
        Log::info($this->log."用户冻结金额：{$use_money}");

        $money_mt = 0;
        $money_fn = 0;
        $money_ss = 0;
        $money_mqd = 0;
        $money_dd = 0;
        $money_uu = 0;
        $money_uu_need = 0;
        $money_uu_total = 0;
        $ss_order_id = "";
        $price_token = '';

        $order = Order::query()->find($this->order->id);

        // *****************************************
        // 判断是否开启UU跑腿(是否存在UU的门店ID，设置是否打开，没用失败信息)
        if (false && $shop->shop_id_uu && $uu_switch && !$this->order->fail_uu && ($order->uu_status === 0)) {
            // $uu = app("uu");
            // $check_uu= $uu->orderCalculate($this->order, $shop);
            // $money_uu = (($check_uu['need_paymoney'] ?? 0)) + 1;
            // $money_uu_total = $check_uu['total_money'] ?? 0;
            // $money_uu_need = $check_uu['need_paymoney'] ?? 0;
            // $price_token = $check_uu['price_token'] ?? '';
            // \Log::info("aaa", [$money_uu, $check_uu['return_code']]);
            // if (isset($check_uu['return_code']) && ($check_uu['return_code'] === 'ok') && ($money_uu >= 1) ) {
            //     $this->money_uu = $money_uu;
            //     $this->services['uu'] = $money_uu;
            //     Log::info($this->log."UU可以，金额：{$money_uu}");
            // } else {
            //     DB::table('orders')->where('id', $this->order->id)->update(['fail_uu' => $check_dd['msg'] ?? "UU校验订单请求失败"]);
            //     Log::info($this->log."UU校验订单请求失败");
            // }
            //
            // // 判断用户金额是否满足UU订单
            // if ($user->money < ($money_uu + $use_money)) {
            //     if ($this->order->status < 20) {
            //         DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
            //     }
            //     dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_mt + $use_money]));
            //     Log::info($this->log."用户金额不足发UU单");
            //     return;
            // }
        } else {
            $log_arr = [
                'shop_id' => $shop->shop_id,
                'uu_status' => $order->uu_status,
                'uu' => $uu_switch,
                'fail_uu' => $this->order->fail_uu
            ];
            Log::info($this->log."跳出UU发单", $log_arr);
        }

        // *****************************************

        // *****************************************
        // 判断是否开启达达跑腿(是否存在美全达的门店ID，设置是否打开，没用失败信息)
        if ($shop->shop_id_dd && $dd_switch && !$this->order->fail_dd && ($order->dd_status === 0)) {
            $dada = app("dada");
            $check_dd= $dada->orderCalculate($shop, $this->order);
            // $money_dd = $check_dd['result']['fee'] ?? 0;
            $money_dd = (($check_dd['result']['fee'] ?? 0)) + 1;
            if (isset($check_dd['code']) && ($check_dd['code'] === 0) && ($money_dd > 1) ) {
                $this->dada_order_id = $check_dd['result']['deliveryNo'];
                $this->money_dd = $money_dd;
                $this->services['dada'] = $money_dd;
                Log::info($this->log."达达可以，金额：{$money_dd}");
            } else {
                DB::table('orders')->where('id', $this->order->id)->update(['fail_dd' => $check_dd['msg'] ?? "达达校验订单请求失败"]);
                Log::info($this->log."达达校验订单请求失败");
            }

            // 判断用户金额是否满足达达订单
            if ($user->money < ($money_dd + $use_money)) {
                if ($this->order->status < 20) {
                    DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                }
                // DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_mt + $use_money]));
                Log::info($this->log."用户金额不足发达达单");
                return;
            }
        } else {
            $log_arr = [
                'shop_id' => $shop->shop_id,
                'dd_status' => $order->dd_status,
                'dada' => $dd_switch,
                'fail_dd' => $this->order->fail_dd
            ];
            Log::info($this->log."跳出达达发单", $log_arr);
        }

        // *****************************************

        // 判断是否开启美全达跑腿(是否存在美全达的门店ID，设置是否打开，没用失败信息)
        if ($shop->shop_id_mqd && $mqd_switch && !$this->order->fail_mqd && ($order->mqd_status === 0)) {
            // $money_mqd = distanceMoney($this->order->distance) + baseMoney($shop->city_level ?: 9) + timeMoney() + dateMoney() + weightMoney($this->order->goods_weight);
            $money_mqd = distanceMoneyMqd($this->order->distance) + baseMoneyMqd($shop->city_level ?: 7) + timeMoneyMqd() + weightMoneyMqd($this->order->goods_weight);
            // $money_mqd = 6;
            $this->services['meiquanda'] = $money_mqd;
            Log::info($this->log."美全达可以，金额：{$money_mqd}");

            // 判断用户金额是否满足美全达订单
            if ($user->money < ($money_mqd + $use_money)) {
                if ($this->order->status < 20) {
                    DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                }
                // DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_mt + $use_money]));
                Log::info($this->log."用户金额不足发美全达单");
                return;
            }
        } else {
            $log_arr = [
                'shop_id' => $shop->shop_id,
                'mqd_status' => $order->mqd_status,
                'meiquanda' => $mqd_switch,
                'fail_meiquanda' => $this->order->fail_mqd
            ];
            Log::info($this->log."跳出美全达发单", $log_arr);
        }

        // ************************************************

        // 判断美团是否可以接单、并加入数组(美团美的ID，设置是否打开，没用失败信息)
        if ($shop->shop_id && $mt_switch && !$this->order->fail_mt && ($order->mt_status === 0)) {
            $meituan = app("meituan");
            $check_mt = $meituan->preCreateByShop($shop, $this->order);
            if (isset($check_mt['code']) && ($check_mt['code'] === 0)) {
                $money_mt = $check_mt['data']['delivery_fee'] ?? 0;
                $money_mt += 1;
                if ($money_mt <= 1) {
                    Log::info($this->log."美团可以，没有返回金额");
                    $money_mt = distanceMoney($this->order->distance) + baseMoney($shop->city_level ?: 9) + timeMoney() + dateMoney() + weightMoney($this->order->goods_weight);
                }
                $this->services['meituan'] = $money_mt;
                // $log_arr = ['money' => $money_mt, 'id' => $this->order->id, 'order_id' => $this->order->order_id];
                Log::info($this->log."美团可以，金额：{$money_mt}");
                // Log::info('发送订单-美团可以', ['money' => $money_mt, 'id' => $this->order->id, 'order_id' => $this->order->order_id]);
            } else {
                DB::table('orders')->where('id', $this->order->id)->update(['fail_mt' => $check_mt['message'] ?? "美团校验订单请求失败"]);
                Log::info($this->log."美团校验订单请求失败");
            }

            // 判断用户金额是否满足美团订单
            if ($user->money < ($money_mt + $use_money)) {
                if ($this->order->status < 20) {
                    DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                }
                // DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_mt + $use_money]));
                Log::info($this->log."用户金额不足发美团单");
                return;
            }
        } else {
            $log_arr = [
                'shop_id' => $shop->shop_id,
                'mt_status' => $order->mt_status,
                'meituan' => $mt_switch,
                'fail_mt' => $this->order->fail_mt
            ];
            Log::info($this->log."跳出美团发单", $log_arr);
        }

        // 判断蜂鸟是否可以接单、并加入数组
        if ($shop->shop_id_fn && $fn_switch && !$this->order->fail_fn && ($order->fn_status === 0)) {
            if ($this->order->type == 11) {
                Log::info($this->log."药柜订单禁止发送蜂鸟");
                // \Log::info('禁止发送蜂鸟-药柜订单', ["time" => time(), "date" => date("Y-m-d H:i:s")]);
            } else {
                if ((time() > strtotime(date("Y-m-d 22:00:00"))) || (time() < strtotime(date("Y-m-d 09:00:00")))) {
                    Log::info($this->log."禁止发送蜂鸟-时间问题");
                    // \Log::info('禁止发送蜂鸟-时间问题', ["time" => time(), "date" => date("Y-m-d H:i:s")]);
                } else {
                    $fengniao = app("fengniao");
                    $check_fn = $fengniao->delivery($shop, $this->order);
                    if (isset($check_fn['code']) && ($check_fn['code'] == 200)) {
                        $money_fn = distanceMoneyFn($this->order->distance) + baseMoneyFn($shop->city_level_fn ?: "G") + timeMoneyFn() + weightMoneyFn($this->order->goods_weight);
                        $this->services['fengniao'] = $money_fn;
                        // Log::info('发送订单-蜂鸟可以', ['money' => $money_fn, 'id' => $this->order->id, 'order_id' => $this->order->order_id]);
                        // $log_arr = ['money' => $money_fn, 'id' => $this->order->id, 'order_id' => $this->order->order_id];
                        Log::info($this->log."蜂鸟可以，金额：{$money_fn}");
                    } else {
                        DB::table('orders')->where('id', $this->order->id)->update(['fail_fn' => $check_fn['msg'] ?? "蜂鸟校验请求失败"]);
                        Log::info($this->log."蜂鸟校验请求失败");
                    }

                    // 判断用户金额是否满足蜂鸟订单
                    if ($user->money < ($money_fn + $use_money)) {
                        if ($this->order->status < 20) {
                            DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                        }
                        // DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                        dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_fn + $use_money]));
                        Log::info($this->log."用户金额不足发蜂鸟单");
                        return;
                    }
                }
            }
        } else {
            $log_arr = [
                'shop_id' => $shop->shop_id_fn,
                'fn_status' => $order->fn_status,
                'fengniao' => $fn_switch,
                'fail_fn' => $this->order->fail_fn
            ];
            Log::info($this->log."跳出蜂鸟发单", $log_arr);
        }

        // 判断闪送是否可以接单、并加入数组
        if ($shop->shop_id_ss && $ss_switch && !$this->order->fail_ss && ($order->ss_status === 0)) {
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

                    $money_ss = (($check_ss['data']['totalFeeAfterSave'] ?? 0) / 100) + 1;
                    $money_log = ($check_ss['data']['totalAmount'] ?? 0) / 100;
                    if ($money_log <= 26) {
                        $money_log = $money_log * 0.8;
                    } else {
                        $money_log = (26 * 0.8) + ($money_log - 26);
                    }
                    $this->money_ss = $money_ss;
                    $ss_order_id = $check_ss['data']['orderNumber'] ?? 0;
                    $this->services['shansong'] = $money_ss;
                    // $log_arr = [
                    //     'shop_id' => $shop->shop_id_ss,
                    //     'shansong' => $setting->shansong,
                    //     'fail_ss' => $this->order->fail_ss
                    // ];
                    // $log_arr = ['money' => $money_ss, 'money_log' => $money_log, 'id' => $this->order->id, 'order_id' => $this->order->order_id];
                    Log::info($this->log."闪送可以，金额：{$money_ss},money_log:{$money_log}");
                    // Log::info('发送订单-闪送可以', ['money' => $money_ss, 'money_log' => $money_log, 'id' => $this->order->id, 'order_id' => $this->order->order_id]);

                    // 判断用户金额是否满足闪送订单
                    if ($user->money < ($money_ss + $use_money)) {
                        if ($this->order->status < 20) {
                            DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                        }
                        // DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                        dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_ss + $use_money]));
                        Log::info($this->log."用户金额不足发闪送单");
                        return;
                    }
                }
            } else {
                DB::table('orders')->where('id', $this->order->id)->update(['fail_ss' => $check_fn['msg'] ?? "闪送校验订单请求失败"]);
                Log::info($this->log."闪送校验订单请求失败");
            }
        } else {
            $log_arr = [
                'shop_id' => $shop->shop_id_ss,
                'ss_status' => $order->ss_status,
                'shansong' => $ss_switch,
                'fail_ss' => $this->order->fail_ss
            ];
            Log::info($this->log."跳出闪送发单", $log_arr);
        }

        // 没有配送服务商
        if (empty($this->services)) {
            if (!$this->order->mt_status && !$this->order->fn_status && !$this->order->ss_status && !$this->order->mqd_status && !$this->order->dd_status) {
                DB::table('orders')->where('id', $this->order->id)->update(['status' => 10]);
            }
            // $this->dingTalk("发送订单失败", "暂无运力");
            // Log::info("[发单]-[id:{$this->order->id},order_id:{$this->order->order_id}]-暂无运力");
            Log::info($this->log."暂无运力");
            return;
        }

        // 更新价格
        $money_arr = [];
        if ($money_dd) {
            $money_arr["money_dd"] = $money_dd;
        }
        if ($money_mqd) {
            $money_arr["money_mqd"] = $money_mqd;
        }
        if ($money_mt) {
            $money_arr["money_mt"] = $money_mt;
        }
        if ($money_fn) {
            $money_arr["money_fn"] = $money_fn;
        }
        if ($money_ss) {
            $money_arr["money_ss"] = $money_ss;
        }
        if ($money_uu) {
            $money_arr["money_uu"] = $money_uu;
        }
        if ($money_uu_total) {
            $money_arr["money_uu_total"] = $money_uu_total;
        }
        if ($money_uu_need) {
            $money_arr["money_uu_need"] = $money_uu_need;
        }
        if ($price_token) {
            $money_arr["price_token"] = $price_token;
        }
        if ($ss_order_id) {
            $money_arr["ss_order_id"] = $ss_order_id;
        }
        DB::table('orders')->where('id', $this->order->id)->update($money_arr);

        $ps = $this->getService();

        if ($ps === "meituan") {
            if ($this->meituan()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                // $this->dingTalk("发送订单失败", "发送订单失败");
                Log::info($this->log."发送美团订单失败");
            }
            return;
        } else if ($ps === "fengniao") {
            if ($this->fengniao()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                // $this->dingTalk("发送订单失败", "发送订单失败");
                Log::info($this->log."发送蜂鸟订单失败");
            }
            return;
        } else if ($ps === "shansong") {
            if ($this->shansong()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                // $this->dingTalk("发送订单失败", "发送订单失败");
                Log::info($this->log."发送闪送订单失败");
            }
            return;
        } else if ($ps === "meiquanda") {
            if ($this->meiquanda()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                // $this->dingTalk("发送订单失败", "发送订单失败");
                Log::info($this->log."发送美全达订单失败");
            }
            return;
        } else if ($ps === "dada") {
            if ($this->dada()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                // $this->dingTalk("发送订单失败", "发送订单失败");
                Log::info($this->log."发送达达订单失败");
            }
            return;
        } else if ($ps === "uu") {
            if ($this->uu()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                Log::info($this->log."发送UU订单失败");
            }
            return;
        }
        Log::info($this->log."发送订单失败，没有返回最小平台");
        // $this->dingTalk("发送订单失败", "没有返回最小平台");
    }

    public function uu()
    {
        $order = Order::find($this->order->id);
        $shop = Shop::find($this->order->shop_id);

        if ($order->status > 30) {
            Log::info($this->log."不能发送UU订单，订单状态大于20，状态：{$order->status}");
        }

        if ($order->uu_status >= 20) {
            Log::info($this->log."不能发送UU订单，达达状态不是0，状态：{$order->uu_status}");
        }

        if ($order->fail_uu) {
            Log::info($this->log."不能发送UU订单，已有UU错误信息");
            return false;
        }

        $uu = app("uu");
        // 发送UU订单
        $result_uu = $uu->addOrder($order, $shop);
        if ($result_uu['return_code'] === 'ok') {
            // 订单发送成功
            Log::info($this->log."发送UU订单成功，返回参数：", [$result_uu]);
            // 写入订单信息
            $update_info = [
                // 'money_uu' => $order->money_uu,
                'uu_order_id' => $result_uu['ordercode'],
                'uu_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 6,
                'order_id' => $this->order->id,
                'des' => '【UU跑腿】平台发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            Log::info($this->log."UU更新创建订单状态成功");
            return true;
        } else {
            $fail_uu = $result_uu['return_msg'] ?? "UU创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_uu' => $fail_uu, 'dd_status' => 3]);
            Log::info($this->log."UU发送订单失败：{$fail_uu}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order));
            } else {
                Log::info($this->log."UU发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function dada()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->status > 30) {
            Log::info($this->log."不能发达达达订单，订单状态大于20，状态：{$order->status}");
        }

        if ($order->dd_status >= 20) {
            Log::info($this->log."不能发送达达订单，达达状态不是0，状态：{$order->mqd_status}");
        }

        if ($order->fail_dd) {
            Log::info($this->log."不能发送达达订单，已有达达错误信息");
            return false;
        }

        $dada = app("dada");
        $money = $this->money_ss;
        // 发送美全达订单
        $result_dd = $dada->createOrder($this->dada_order_id);
        if ($result_dd['code'] === 0) {
            // 订单发送成功
            Log::info($this->log."发送达达订单成功，返回参数：", [$result_dd]);
            // 写入订单信息
            $update_info = [
                'money_mqd' => $money,
                'dd_order_id' => $this->order->order_id,
                'dd_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 5,
                'order_id' => $this->order->id,
                'des' => '【达达】跑腿，发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            Log::info($this->log."达达更新创建订单状态成功");
            return true;
        } else {
            $fail_dd = $result_dd['message'] ?? "达达创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_dd' => $fail_dd, 'dd_status' => 3]);
            Log::info($this->log."达达发送订单失败：{$fail_dd}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order));
            } else {
                Log::info($this->log."达达发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function meiquanda()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->status > 30) {
            Log::info($this->log."不能发送美全达订单，订单状态大于20，状态：{$order->status}");
        }

        if ($order->mqd_status >= 20) {
            Log::info($this->log."不能发送美全达订单，美团状态不是0，状态：{$order->mqd_status}");
        }

        if ($order->fail_mqd) {
            Log::info($this->log."不能发送美全达订单，已有美全达错误信息");
            return false;
        }
        $shop = Shop::query()->find($this->order->shop_id);

        $meiquanda = app("meiquanda");
        $distance = distanceMoneyMqd($this->order->distance);
        $base = baseMoneyMqd($shop->city_level ?: 9);
        $time_money = timeMoneyMqd();
        // $date_money = dateMoney();
        $date_money = 0;
        $weight_money = weightMoneyMqd($this->order->goods_weight);
        $money = $base + $time_money + $date_money + $distance + $weight_money;
        // 发送美全达订单
        $result_mqd = $meiquanda->createOrder($shop, $this->order);
        if ($result_mqd['code'] === 100) {
            // 订单发送成功
            Log::info($this->log."发送美全达订单成功，返回参数：", [$result_mqd]);
            $mqd_order_info = $meiquanda->getOrderInfo($result_mqd['data']['trade_no'] ?? "");
            if (!empty($mqd_order_info['data']['merchant_pay_fee']) && $mqd_order_info['data']['merchant_pay_fee'] > 0) {
                Log::info($this->log."获取美全达订单金额成功");
                $money = $mqd_order_info['data']['merchant_pay_fee'];
            }
            // 写入订单信息
            $update_info = [
                'money_mqd' => $money,
                'mqd_order_id' => $result_mqd['data']['trade_no'],
                'mqd_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 4,
                'order_id' => $this->order->id,
                'des' => '【美全达】跑腿，发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            Log::info($this->log."美全达更新创建订单状态成功");
            return true;
        } else {
            $fail_mqd = $result_mqd['message'] ?? "美全达创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_mqd' => $fail_mqd, 'mqd_status' => 3]);
            Log::info($this->log."美全达发送订单失败：{$fail_mqd}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order));
            } else {
                Log::info($this->log."美全达发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function meituan()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->status > 30) {
            Log::info($this->log."不能发送美团订单，订单状态大于20，状态：{$order->status}");
        }

        if ($order->mt_status >= 20) {
            Log::info($this->log."不能发送美团订单，美团状态不是0，状态：{$order->mt_status}");
        }

        if ($order->fail_mt) {
            Log::info($this->log."不能发送美团订单，已有美团错误信息");
            return false;
        }
        $shop = Shop::query()->find($this->order->shop_id);

        $meituan = app("meituan");
        $distance = distanceMoney($this->order->distance);
        $base = baseMoney($shop->city_level ?: 9);
        $time_money = timeMoney();
        $date_money = dateMoney();
        $weight_money = weightMoney($this->order->goods_weight);

        $money = $base + $time_money + $date_money + $distance + $weight_money;
        // 发送美团订单
        $result_mt = $meituan->createByShop($shop, $this->order);
        if ($result_mt['code'] === 0) {
            // 订单发送成功
            Log::info($this->log."发送美团订单成功，返回参数：", [$result_mt]);
            if (!empty($result_mt['data']['delivery_fee']) && $result_mt['data']['delivery_fee'] > 0) {
                $money = $result_mt['data']['delivery_fee'] + 1;
            }
            // 写入订单信息
            $update_info = [
                'money_mt' => $money,
                'mt_order_id' => $result_mt['data']['mt_peisong_id'],
                'mt_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 1,
                'order_id' => $this->order->id,
                'des' => '【美团】跑腿，发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            Log::info($this->log."美团更新创建订单状态成功");
            return true;
        } else {
            $fail_mt = $result_mt['message'] ?? "美团创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_mt' => $fail_mt, 'mt_status' => 3]);
            Log::info($this->log."美团发送订单失败：{$fail_mt}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order));
            } else {
                Log::info($this->log."美团发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function fengniao()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->status > 30) {
            Log::info($this->log."不能发送蜂鸟订单，订单状态大于20，状态：{$order->status}");
        }

        if ($order->fn_status >= 20) {
            Log::info($this->log."不能发送蜂鸟订单，蜂鸟状态不是0，状态：{$order->fn_status}");
        }

        if ($order->fail_fn) {
            Log::info($this->log."不能发送蜂鸟订单，已有蜂鸟错误信息");
            return false;
        }

        $shop = Shop::query()->find($this->order->shop_id);

        $fengniao = app("fengniao");
        $distance = distanceMoneyFn($this->order->distance);
        $base = baseMoneyFn($shop->city_level_fn ?: "G");
        $time_money = timeMoneyFn();
        $date_money = 0;
        $weight_money = weightMoneyFn($this->order->goods_weight);

        $money = $base + $time_money + $date_money + $distance + $weight_money;

        $result_fn = $fengniao->createOrder($shop, $this->order);
        if ($result_fn['code'] == 200) {
            // 订单发送成功
            Log::info($this->log."发送蜂鸟订单成功，返回参数：", [$result_fn]);
            $fn_order_info = $fengniao->getOrder($this->order->order_id);
            if ((!empty($fn_order_info['code'])) && ($fn_order_info['code'] == 200)) {
                if (!empty($fn_order_info['data']['order_total_delivery_cost']) && $fn_order_info['data']['order_total_delivery_cost'] > 0) {
                    $money = $fn_order_info['data']['order_total_delivery_cost'] + 1;
                }
            }
            // 写入订单信息
            $update_info = [
                'money_fn' => $money,
                'fn_order_id' => $fn_order_info['data']['tracking_id'] ?? $this->order->order_id,
                'fn_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 2,
                'order_id' => $this->order->id,
                'des' => '【蜂鸟】跑腿，发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            Log::info($this->log."蜂鸟更新创建订单状态成功");
            return true;
        } else {
            $fail_fn = $result_fn['msg'] ?? "蜂鸟创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_fn' => $fail_fn, 'fn_status' => 3]);
            Log::info($this->log."蜂鸟发送订单失败：{$fail_fn}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order));
            } else {
                Log::info($this->log."蜂鸟发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function shansong()
    {
        $order = Order::query()->find($this->order->id);

        if ($order->status > 30) {
            Log::info($this->log."不能发送闪送订单，订单状态大于20，状态：{$order->status}");
            return false;
        }

        if ($order->ss_status >= 20) {
            Log::info($this->log."不能发送闪送订单，闪送状态不是0，状态：{$order->ss_status}");
            return false;
        }

        if ($order->fail_ss) {
            Log::info($this->log."不能发送闪送订单，已有闪送错误信息");
            return false;
        }

        $shansong = app("shansong");
        $money = $this->money_ss;

        // 发送闪送订单
        $result_ss = $shansong->createOrder($order->ss_order_id);
        if ($result_ss['status'] === 200) {
            // 订单发送成功
            Log::info($this->log."发送闪送订单成功，金额：{$money}。返回参数：", [$result_ss]);
            // 写入订单信息
            $update_info = [
                'money_ss' => $money,
                'ss_order_id' => $order->ss_order_id,
                'ss_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 3,
                'order_id' => $this->order->id,
                'des' => '【闪送】跑腿，发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            Log::info($this->log."闪送更新创建订单状态成功");
            return true;
        } else {
            $fail_ss = $result_ss['msg'] ?? "闪送创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_ss' => $fail_ss, 'ss_status' => 3]);
            Log::info($this->log."闪送发送订单失败：{$fail_ss}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order));
            } else {
                Log::info($this->log."闪送发送订单失败，没有平台了");
            }
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
