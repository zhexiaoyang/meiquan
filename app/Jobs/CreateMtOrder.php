<?php

namespace App\Jobs;

use App\Libraries\DingTalk\DingTalkRobot;
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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // $shop = DB::table('shops')->where('id', $this->order->shop_id)->first();
        // $user = DB::table('users')->where('id', $shop->user_id)->first();
        $shop = Shop::query()->find($this->order->shop_id);
        $user = User::query()->find($shop->user_id ?? 0);

        if ($shop && $user) {
            if ($user->money > 5) {
                // $this->order->money &&  DB::table('users')->where('id', $user->id)->where('money', '>', $this->order->money)->update(['money' => $user->money - $this->order->money])；
                $send = false;

                // 创建订单-开始

                // 美团订单
                Log::info("PS-美团", ['ps' => $this->order->ps, 'send' => $send]);
                $_order = Order::query()->find($this->order->id);
                Log::info("PS-美团2", ['ps' => $_order->ps, 'send' => $send]);
                if ($shop->shop_id && !$this->order->fail_mt && !$send && !$_order->ps) {
                    $meituan = app("meituan");
                    $check_mt = $meituan->check($shop, $this->order);
                    if (isset($check_mt['code']) && ($check_mt['code'] === 0) ) {

                        $distance = distanceMoney($shop, $this->order->receiver_lng, $this->order->receiver_lat);
                        $base = baseMoney($shop->city_level ?: 9);
                        $time_money = timeMoney();
                        $date_money = dateMoney();
                        $weight_money = weightMoney($this->order->goods_weight);

                        $money = $base + $time_money + $date_money + $distance + $weight_money;

                        if ($money >= 0 && ($user->money > $money) && DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money - $money])) {
                            Log::info('美团订单-扣款成功', ['order_id' => $this->order->id, 'user_id' => $user->id, 'money' => $money]);
                            $send = true;
                            // 发送美团订单
                            $result_mt = $meituan->createByShop($shop, $this->order);
                            if ($result_mt['code'] === 0) {
                                // 订单发送成功
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
                            } else {
                                $fail_mt = $result_mt['message'] ?? "美团创建订单失败";
                                DB::table('orders')->where('id', $this->order->id)->update(['fail_mt' => $fail_mt]);
                                DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money + $money]);
                                Log::info('美团发送创建失败-把钱返给用户', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                            }
                        } else {
                            Log::info('美团订单-余额不足-扣款失败', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
                        }
                    } else {
                        $this->order->fail_mt = $check_mt['message'] ?? "美团校验订单请求失败";
                        $this->order->save();
                    }
                }

                $status_fn = true;

                if ((time() > strtotime(date("Y-m-d 22:00:00"))) || (time() < strtotime(date("Y-m-d 09:00:00")))) {
                    \Log::info('禁止发送蜂鸟', ["time" => time(), "date" => date("Y-m-d H:i:s")]);
                    $dingding = new DingTalkRobot();
                    $dingding->accessToken = "f9badd5f617a986f267295afded03ee6c936e5f9fd0e381593b02fce5543c323";
                    $res = $dingding->sendMarkdownMsg("关闭蜂鸟发单", "date：" . date("Y-m-d H:i:s") . ",时间:" . time());
                    \Log::info('钉钉日志发送状态', [$res]);
                    $status_fn = false;
                }

                // 蜂鸟订单
                Log::info("PS-蜂鸟", ['ps' => $this->order->ps, 'send' => $send]);
                $_order = Order::query()->find($this->order->id);
                Log::info("PS-蜂鸟2", ['ps' => $_order->ps, 'send' => $send]);
                if ($shop->shop_id_fn && !$this->order->fail_fn && !$send && !$_order->ps && $status_fn) {
                    $fengniao = app("fengniao");
                    $check_fn = $fengniao->delivery($shop, $this->order);
                    if (isset($check_fn['code']) && ($check_fn['code'] == 200) ) {

                        $distance = distanceMoneyFn($shop, $this->order->receiver_lng, $this->order->receiver_lat);
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
                                $dingding = new DingTalkRobot();
                                $dingding->accessToken = "98d212d8ab60c3b48d17e28d4812db1179e8fba03c55b7cf546e250087d6dac2";
                                $res = $dingding->sendMarkdownMsg("发送蜂鸟订单了", "发送蜂鸟订单了-订单号：" . $this->order->order_id);
                                \Log::info('钉钉日志发送状态', [$res]);
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
                            } else {
                                $fail_fn = $result_fn['msg'] ?? "蜂鸟创建订单失败";
                                DB::table('orders')->where('id', $this->order->id)->update(['fail_fn' => $fail_fn]);
                                DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money + $money]);
                                Log::info('蜂鸟发送创建失败-把钱返给用户', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                            }
                        } else {
                            Log::info('蜂鸟订单-余额不足-扣款失败', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
                        }
                    } else {
                        $this->order->fail_fn = $check_fn['msg'] ?? "蜂鸟校验请求失败";
                        $this->order->save();
                    }
                }

                // 闪送订单
                Log::info("PS-闪送", ['ps' => $this->order->ps, 'send' => $send]);
                $_order = Order::query()->find($this->order->id);
                Log::info("PS-闪送2", ['ps' => $_order->ps, 'send' => $send]);
                if ($shop->shop_id_ss && !$this->order->fail_ss && !$send && !$_order->ps) {
                    $shansong = app("shansong");
                    $check_ss = $shansong->orderCalculate($shop, $this->order);
                    if (isset($check_ss['status']) && ($check_ss['status'] === 200) ) {

                        $distance = 0;
                        $base = 0;
                        $time_money = 0;
                        $date_money = 0;
                        $weight_money = 0;

                        if (isset($check_ss['data']['feeInfoList']) && !empty($check_ss['data']['feeInfoList'])) {
                            foreach ($check_ss['data']['feeInfoList'] as $v) {
                                if ($v['type'] == 1) {
                                    $base = $v['fee'] / 100 ?? 0;
                                }
                                if ($v['type'] == 2) {
                                    $weight_money = $v['fee'] / 100 ?? 0;
                                }
                                if ($v['type'] == 7) {
                                    $time_money = $v['fee'] / 100 ?? 0;
                                }
                            }
                            // $money = ($check_ss['data']['totalAmount'] ?? 0) * 0.85 / 100;

                            $money = ($check_ss['data']['totalAmount'] ?? 0) / 100;

                            if ($money <= 26) {
                                $money = $money * 0.8;
                            } else {
                                $money = (26 * 0.8) + ($money - 26);
                            }

                            $order_id = $check_ss['data']['orderNumber'] ?? 0;
                        }

                        if ($order_id && ($money >= 0) && ($user->money > $money) && DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money - $money])) {
                            Log::info('闪送订单-扣款成功', ['order_id' => $this->order->id, 'user_id' => $user->id, 'money' => $money]);
                            $send = true;
                            // 发送闪送订单
                            $result_ss = $shansong->createOrder($order_id);
                            if ($result_ss['status'] === 200) {
                                // 订单发送成功
                                // 写入订单信息
                                $update_info = [
                                    'money' => $money,
                                    'base_money' => $base,
                                    'distance_money' => $distance,
                                    'weight_money' => $weight_money,
                                    'time_money' => $time_money,
                                    'date_money' => $date_money,
                                    'peisong_id' => $order_id,
                                    'status' => 20,
                                    'ps' => 3
                                ];
                                DB::table('orders')->where('id', $this->order->id)->update($update_info);
                                Log::info('闪送订单-更新创建订单状态成功');
                            } else {
                                $fail_ss = $result_ss['msg'] ?? "闪送创建订单失败";
                                DB::table('orders')->where('id', $this->order->id)->update(['fail_ss' => $fail_ss]);
                                DB::table('users')->where('id', $user->id)->where('money', '>', $money)->update(['money' => $user->money + $money]);
                                Log::info('闪送发送创建失败-把钱返给用户', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                            }
                        } else {
                            Log::info('闪送订单-余额不足-扣款失败', ['order_id' => $this->order->id, 'user_id' => $user->id]);
                            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
                        }
                    } else {
                        $this->order->fail_ss = $check_ss['msg'] ?? "闪送校验订单请求失败";
                        $this->order->save();
                    }
                }

                // 创建订单-结束

                if ($send) {
                    $user = DB::table('users')->where('id', $shop->user_id)->first();
                    if ($user->money < 20) {
                        dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
                    }
                } else {
                    $this->order->status = 10;
                    $this->order->save();
                    Log::info('发送订单-暂无运力', []);
                    // DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
                    // Log::info('创建发送失败，将钱返回给用户', ["user_id" => $shop->user_id, "order_id" => $this->order->id, "money" => $this->order->money]);
                }
            } else {
                dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));
            }
        }
        // $meituan = app("meituan");
        // $params = [
        //     'delivery_id' => $this->order->delivery_id,
        //     'order_id' => $this->order->order_id,
        //     'shop_id' => $this->order->shop_id,
        //     'delivery_service_code' => "4011",
        //     'receiver_name' => $this->order->receiver_name,
        //     'receiver_address' => $this->order->receiver_address,
        //     'receiver_phone' => $this->order->receiver_phone,
        //     'receiver_lng' => $this->order->receiver_lng * 1000000,
        //     'receiver_lat' => $this->order->receiver_lat * 1000000,
        //     'coordinate_type' => $this->order->coordinate_type,
        //     'goods_value' => $this->order->goods_value,
        //     'goods_weight' => $this->order->goods_weight,
        // ];
        //
        // Log::info('发送配送订单-开始');
        // $result = $meituan->createByShop($params);
        // Log::info('发送配送订单-结束');
        //
        // if ($result['code'] === 0) {
        //     DB::table('orders')->where('id', $this->order->id)->update(['peisong_id' => $result['data']['peisong_id'], 'status' => 0]);
        // } else {
        //     $log = MoneyLog::query()->where('order_id', $this->order->id)->first();
        //     if ($log) {
        //         $log->status = 2;
        //         $log->save();
        //         $shop = DB::table('shops')->where('shop_id', $this->order->shop_id)->first();
        //         if (isset($shop->user_id) && $shop->user_id) {
        //             DB::table('users')->where('id', $shop->user_id)->increment('money', $this->order->money);
        //             \Log::info('创建订单失败，将钱返回给用户', [$this->order->money]);
        //         } else {
        //             \Log::info('创建订单失败，门店不存在', [$shop]);
        //         }
        //     }
        //     DB::table('orders')->where('id', $this->order->id)->update(['failed' => $result['message'], 'status' => -1]);
        // }
    }
}
