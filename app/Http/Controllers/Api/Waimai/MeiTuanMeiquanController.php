<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use App\Jobs\CreateMtOrder;
use App\Jobs\PushDeliveryOrder;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MeiTuanMeiquanController extends Controller
{
    /**
     * 推送已支付订单回调
     * @param Request $request
     * @author zhangzhen
     * @data 2021/3/12 10:11 下午
     */
    public function pay(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送已支付订单回调URL]-全部参数：", $request->all());
        return json_encode(['data' => 'ok']);
    }

    public function create(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送已确认订单回调]-全部参数：", $request->all());
        $mt_shop_id = $request->get("app_poi_code", "");
        $mt_order_id = $request->get("wm_order_id_view", "");

        if (!$mt_shop_id || !$mt_order_id) {
            return json_encode(['data' => 'ok']);
        }

        if (Order::query()->where("order_id", $mt_order_id)->first()) {
            Log::info("【外卖-美团服务商】（{$mt_order_id}）：美团服务商异常-订单已存在");
            return json_encode(['data' => 'ok']);
        }

        // 创建跑腿订单
        if ($shop = Shop::query()->where("mt_shop_id", $mt_shop_id)->first()) {
            Log::info("【外卖-美团服务商】（{$mt_order_id}）：正在创建跑腿订单");
            $mt_status = $request->get("status", 0);
            $pick_type = $request->get("pick_type", 0);
            $recipient_address = urldecode($request->get("recipient_address", ""));

            if ($pick_type === 1) {
                Log::info("【外卖-美团服务商】（{$mt_order_id}）：到店自取订单，不创建跑腿订单");
                return json_encode(['data' => 'ok']);
            }

            if (strstr($recipient_address, "到店自取")) {
                Log::info("【外卖-美团服务商】（{$mt_order_id}）：到店自取订单，不创建跑腿订单");
                return json_encode(['data' => 'ok']);
            }

            if (Order::where('order_id', $mt_order_id)->first()) {
                Log::info("【外卖-美团服务商】（{$mt_order_id}）：跑腿订单已存在");
                return json_encode(['data' => 'ok']);
            }

            $status = 0;
            if ($mt_status < 4) {
                $status = -30;
            }
            if ($mt_status > 4) {
                $status = -10;
            }

            $weight = $request->get("total_weight", 0) ?? 0;
            $delivery_time = $request->get("delivery_time", 0);
            // 创建订单数组
            $order_pt = [
                'delivery_id' => $mt_order_id,
                'user_id' => $shop->user_id,
                'order_id' => $mt_order_id,
                'shop_id' => $shop->id,
                'delivery_service_code' => "4011",
                'receiver_name' => urldecode($request->get("recipient_name", "")) ?? "无名客人",
                'receiver_address' => $recipient_address,
                'receiver_phone' => $request->get("recipient_phone", ""),
                'receiver_lng' => $request->get("longitude", 0),
                'receiver_lat' => $request->get("latitude", 0),
                'coordinate_type' => 0,
                'goods_value' => $request->get("total", 0),
                // 'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                'goods_weight' => 3,
                'day_seq' => $request->get("day_seq", 0),
                'platform' => 1,
                'type' => 31,
                'status' => $status,
                'order_type' => 0
            ];

            // 判断是否预约单
            if ($delivery_time > 0) {
                if ($status === 0) {
                    $order_pt['status'] = 3;
                }
                $order_pt['order_type'] = 1;
                $order_pt['expected_pickup_time'] = $delivery_time - 3600;
                $order_pt['expected_delivery_time'] = $delivery_time;
            }

            $order = new Order($order_pt);
            // 保存订单
            if ($order->save()) {
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "（美团外卖）自动创建跑腿订单：{$order->order_id}"
                ]);
                Log::info("【外卖-美团服务商】（{$mt_order_id}）：跑腿订单创建完毕");
                if ($status === 0) {
                    if ($order->order_type) {
                        $qu = 2400;
                        if ($order->distance <= 2) {
                            $qu = 1800;
                        }

                        dispatch(new PushDeliveryOrder($order, ($order->expected_delivery_time - time() - $qu)));
                        Log::info("【外卖-美团服务商】（{$mt_order_id}）：美团创建预约订单成功");
                        // \Log::info('美团创建预约订单成功', ['id' => $order->id, 'order_id' => $order->order_id]);

                        // $ding_notice = app("ding");
                        // $logs = [
                        //     "des" => "接到预订单：" . $qu,
                        //     "datetime" => date("Y-m-d H:i:s"),
                        //     "order_id" => $order->order_id,
                        //     "status" => $order->status,
                        //     "ps" => $order->ps
                        // ];
                        // $ding_notice->sendMarkdownMsgArray("接到美团预订单", $logs);
                    } else {
                        Log::info("【外卖-美团服务商】（{$mt_order_id}）：派单单成功");
                        $order->send_at = date("Y-m-d H:i:s");
                        $order->status = 8;
                        $order->save();
                        dispatch(new CreateMtOrder($order, config("ps.order_delay_ttl")));
                    }
                }
            }
        } else {
            Log::info("【外卖-美团服务商】（{$mt_order_id}）：未开通自动接单");
            // Log::info('外卖-美团服务商-未开通自动接单', ['shop_id' => $mt_shop_id, 'shop_name' => urldecode($request->get("wm_poi_name", ""))]);
        }
        // return json_encode(['data' => 'ok']);
        Log::info("【外卖-美团服务商】（{$mt_order_id}）：美团服务商异常-到底了");
    }

    public function cancel(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送用户或客服取消订单回调]-全部参数：", $request->all());
        $order_id = $request->get("order_id", "");

        if ($order = Order::query()->where("order_id", $order_id)->first()) {
            $order = Order::query()->where('order_id', $order_id)->first();
            \Log::info("[外卖-美团服务商-接口取消订单]-[订单号: {$order_id}]-开始");

            if (!$order) {
                \Log::info("[外卖-美团服务商-接口取消订单]-[订单号: {$order_id}]-订单不存在");
                // \Log::info('[订单-美团接口取消订单]-订单未找到', ['请求参数' => $request->all()]);
                return $this->error("订单不存在");
            }

            $ps = $order->ps;

            if ($order->status == 99) {
                // 已经是取消状态
                return json_encode(['data' => 'ok']);
            } elseif ($order->status == 80) {
                // 异常状态
                return json_encode(['data' => 'ok']);
            } elseif ($order->status == 70) {
                // 已经完成
                return $this->error("订单已经完成，不能取消");
            } elseif (in_array($order->status, [40, 50, 60])) {
                $dd = app("ding");
                if ($ps == 1) {
                    $meituan = app("meituan");
                    $result = $meituan->delete([
                        'delivery_id' => $order->delivery_id,
                        'mt_peisong_id' => $order->mt_order_id,
                        'cancel_reason_id' => 399,
                        'cancel_reason' => '其他原因',
                    ]);
                    if ($result['code'] === 0) {
                        try {
                            DB::transaction(function () use ($order) {
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团）取消美团跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'mt_status' => 99,
                                ]);
                                \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团）取消【美团】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "美团",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团接口取消订单】取消美团订单返回失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团接口取消订单，取消美团订单返回失败", $logs);
                    }
                } elseif ($ps == 2) {
                    $fengniao = app("fengniao");
                    $result = $fengniao->cancelOrder([
                        'partner_order_code' => $order->order_id,
                        'order_cancel_reason_code' => 2,
                        'order_cancel_code' => 9,
                        'order_cancel_time' => time() * 1000,
                    ]);
                    if ($result['code'] == 200) {
                        try {
                            DB::transaction(function () use ($order) {
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团）取消蜂鸟跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'fn_status' => 99,
                                ]);
                                \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团）取消【蜂鸟】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "蜂鸟",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团接口取消订单】取消蜂鸟订单返回失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团接口取消订单，取消蜂鸟订单返回失败", $logs);
                    }
                } elseif ($ps == 3) {
                    $shansong = app("shansong");
                    $result = $shansong->cancelOrder($order->ss_order_id);
                    if ($result['status'] == 200) {
                        try {
                            DB::transaction(function () use ($order) {
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->receive_at)) {
                                    $jian_money = 2;
                                    $jian = time() - strtotime($order->receive_at);
                                    if ($jian >= 480) {
                                        $jian_money = 5;
                                    }
                                    if (!empty($order->take_at)) {
                                        $jian_money = 5;
                                    }
                                }

                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团）取消闪送跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "取消闪送跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'ss_status' => 99,
                                ]);
                                // $current_user->increment('money', ($order->money - $jian_money));
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团）取消【闪送】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "闪送",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团接口取消订单】取消蜂鸟订单返回失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团接口取消订单，取消闪送订单返回失败", $logs);
                    }
                } elseif ($ps == 4) {
                    $fengniao = app("meiquanda");
                    $result = $fengniao->repealOrder($order->mqd_order_id);
                    if ($result['code'] == 100) {
                        try {
                            DB::transaction(function () use ($order) {
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团）取消美全达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'mqd_status' => 99,
                                ]);
                                \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团）取消【美全达】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "美全达",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团接口取消订单】取消美全达订单返回失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团接口取消订单，取消美全达订单返回失败", $logs);
                    }
                } elseif ($ps == 5) {
                    $dada = app("dada");
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] == 0) {
                        try {
                            DB::transaction(function () use ($order) {
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团）取消达达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'dd_status' => 99,
                                ]);
                                \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团）取消【达达】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "达达",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消美全达订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团接口取消订单】取消达达订单返回失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团接口取消订单，取消达达订单返回失败", $logs);
                    }
                } elseif ($ps == 6) {
                    $uu = app("uu");
                    $result = $uu->cancelOrder($order);
                    if ($result['return_code'] == 'ok') {
                        try {
                            DB::transaction(function () use ($order) {
                                // 用户余额日志
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->take_at)) {
                                    $jian_money = 3;
                                }
                                // 当前用户
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团外卖）取消UU跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "（美团外卖）取消UU跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'uu_status' => 99,
                                    'cancel_at' => date("Y-m-d H:i:s")
                                ]);
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团外卖）取消【UU跑腿】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "UU",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:UU]-取消UU订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】取消UU订单返回失败",
                            "id" => $order->id,
                            "ps" => "UU",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消UU订单返回失败", $logs);
                    }
                } elseif ($ps == 7) {
                    $sf = app("shunfeng");
                    $result = $sf->cancelOrder($order);
                    if ($result['error_code'] == 0) {
                        try {
                            DB::transaction(function () use ($order, $result) {
                                // 用户余额日志
                                // 计算扣款
                                $jian_money = isset($result['result']['deduction_detail']['deduction_fee']) ? ($result['result']['deduction_detail']['deduction_fee']/100) : 0;
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-扣款金额：{$jian_money}");
                                // 当前用户
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（美团外卖）取消顺丰跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::query()->create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "（美团外卖）取消顺丰跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'sf_status' => 99,
                                    'cancel_at' => date("Y-m-d H:i:s")
                                ]);
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（美团外卖）取消【顺丰跑腿】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "顺丰",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-取消顺丰订单返回失败", [$result]);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】取消顺丰订单返回失败",
                            "id" => $order->id,
                            "ps" => "顺丰",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消顺丰订单返回失败", $logs);
                    }
                }
                return json_encode(['data' => 'ok']);
            } elseif (in_array($order->status, [20, 30])) {
                // 没有骑手接单，取消订单
                if (in_array($order->mt_status, [20, 30])) {
                    $meituan = app("meituan");
                    $result = $meituan->delete([
                        'delivery_id' => $order->delivery_id,
                        'mt_peisong_id' => $order->mt_order_id,
                        'cancel_reason_id' => 399,
                        'cancel_reason' => '其他原因',
                    ]);
                    if ($result['code'] == 0) {
                        $order->status = 99;
                        $order->mt_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【美团】跑腿订单"
                        ]);
                    }
                }
                if (in_array($order->fn_status, [20, 30])) {
                    $fengniao = app("fengniao");
                    $result = $fengniao->cancelOrder([
                        'partner_order_code' => $order->order_id,
                        'order_cancel_reason_code' => 2,
                        'order_cancel_code' => 9,
                        'order_cancel_time' => time() * 1000,
                    ]);
                    if ($result['code'] == 200) {
                        $order->status = 99;
                        $order->fn_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【蜂鸟】跑腿订单"
                        ]);
                    }
                }
                if (in_array($order->ss_status, [20, 30])) {
                    $shansong = app("shansong");
                    $result = $shansong->cancelOrder($order->ss_order_id);
                    if ($result['status'] == 200) {
                        $order->status = 99;
                        $order->ss_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【闪送】跑腿订单"
                        ]);
                    }
                }
                if (in_array($order->mqd_status, [20, 30])) {
                    $meiquanda = app("meiquanda");
                    $result = $meiquanda->repealOrder($order->mqd_order_id);
                    if ($result['code'] == 100) {
                        $order->status = 99;
                        $order->mqd_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【美全达】跑腿订单"
                        ]);
                    }
                }
                if (in_array($order->dd_status, [20, 30])) {
                    $dada = app("dada");
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] == 0) {
                        $order->status = 99;
                        $order->dd_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【达达】跑腿订单"
                        ]);
                    }
                }
                if (in_array($order->uu_status, [20, 30])) {
                    $uu = app("uu");
                    $result = $uu->cancelOrder($order);
                    if ($result['return_code'] == 'ok') {
                        $order->status = 99;
                        $order->uu_status = 99;
                        $order->cancel_at = date("Y-m-d H:i:s");
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【UU】跑腿订单"
                        ]);
                    }
                }
                if (in_array($order->sf_status, [20, 30])) {
                    $sf = app("shunfeng");
                    $result = $sf->cancelOrder($order);
                    if ($result['error_code'] == 0) {
                        $order->status = 99;
                        $order->sf_status = 99;
                        $order->cancel_at = date("Y-m-d H:i:s");
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（美团）取消【顺丰】跑腿订单"
                        ]);
                    }
                }
                return json_encode(['data' => 'ok']);
            } else {
                // 状态小于20，属于未发单，直接操作取消
                if ($order->status < 0) {
                    \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order_id}]-[订单状态：{$order->status}]-订单状态小于0");
                    $order->status = -10;
                } else {
                    $order->status = 99;
                }
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "（美团）取消跑腿订单"
                ]);
                \Log::info("[外卖-美团服务商接口取消订单]-[订单号: {$order_id}]-未配送");
                return json_encode(['data' => 'ok']);
            }
        }
        return json_encode(['data' => 'ok']);
    }

    // 门店绑定授权
    public function bind(Request $request)
    {
        \Log::info("门店绑定授权", $request->all());
        $op_type = $request->get("op_type", 0);
        $poi_info = json_decode(urldecode($request->get("poi_info", "")), true);
        $shop_id = $poi_info["appPoiCode"] ?? "";
        \Log::info("门店绑定授权-参数|类型：{$op_type}|门店ID：{$shop_id}|");
        if ($op_type && $shop_id) {
            if (!Cache::lock("meiquan_$shop_id", 5)->get()) {
                \Log::info("门店绑定授权-锁住了");
                return $this->success(["data" => "ok"]);
            }
            if ($op_type == 1) {
                $meituan = app("meiquan");
                $key = 'mtwm:shop:auth:' . $shop_id;
                $key_ref = 'mtwm:shop:auth:ref:' . $shop_id;
                $res = $meituan->waimaiAuthorize($shop_id);
                if (!empty($res['access_token'])) {
                    $access_token = $res['access_token'];
                    $refresh_token = $res['refresh_token'];
                    Cache::put($key, $access_token, $res['expires_in'] - 100);
                    Cache::forever($key_ref, $refresh_token);
                }
            }
            if ($op_type == 2) {
                $key = 'mtwm:shop:auth:' . $shop_id;
                $key_ref = 'mtwm:shop:auth:ref:' . $shop_id;
                Cache::forget($key);
                Cache::forget($key_ref);
            }
            Cache::lock("meiquan_$shop_id")->release();
        }

        return $this->success(["data" => "ok"]);
    }

    public function refund(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送全额退款信息回调]-全部参数：", $request->all());
        return json_encode(['data' => 'ok']);
    }
    public function refundPart(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送部分退款信息回调]-全部参数：", $request->all());
        return json_encode(['data' => 'ok']);
    }
    public function logistics(Request $request)
    {
        \Log::info("[外卖-美团服务商]-[推送美配订单配送状态回调]-全部参数：", $request->all());
        return json_encode(['data' => 'ok']);
    }

    public function test(Request $request)
    {
        $id = $request->get("id");
        $type = $request->get("type");
        $meiquan = app("meiquan");
        $res = '';

        // $res = $meiquan->waimaiAuthorize(['response_type' => 'token','app_poi_code' => '6167_2705857']);
        // return $res;




        if ($type == 1) {
            $res = $meiquan->waimaiOrderConfirm(['order_id' => $id,'access_token' => 'token_nZ-Mtc7LVt_RbVaW-2hJ5g','app_poi_code' => '6167_2694600']);
        } elseif ($type == 2) {
            $res = $meiquan->waimaiOrderCancel(['order_id' => $id,'access_token' => 'token_nZ-Mtc7LVt_RbVaW-2hJ5g','app_poi_code' => '6167_2705857']);
        } elseif ($type == 3) {
            $res = $meiquan->waimaiOrderRefundAgree(['order_id' => $id, 'reason' => '同意退款','access_token' => 'token_nZ-Mtc7LVt_RbVaW-2hJ5g','app_poi_code' => '6167_2705857']);
        } elseif ($type == 4) {
            $res = $meiquan->waimaiOrderRefundReject(['order_id' => $id, 'reason' => '拒绝退款','access_token' => 'token_nZ-Mtc7LVt_RbVaW-2hJ5g','app_poi_code' => '6167_2705857']);
        } elseif ($type == 5) {
            $res = $meiquan->waimaiOrderBatchPullPhoneNumber(['order_id' => $id, 'offset' => 0, 'limit' => 100,'access_token' => 'token_nZ-Mtc7LVt_RbVaW-2hJ5g','app_poi_code' => '6167_2705857']);
        } elseif ($type == 6) {
            $res = $meiquan->waimaiOrderReviewAfterSales(['wm_order_id_view' => $id, 'review_type' => 1,'access_token' => 'token_nZ-Mtc7LVt_RbVaW-2hJ5g','app_poi_code' => '6167_2705857']);
        }

        return $res;

    }
}
