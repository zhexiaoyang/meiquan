<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use App\Jobs\CreateMtOrder;
use App\Jobs\PushDeliveryOrder;
use App\Libraries\Ele\Api\Tool;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EleOrderController extends Controller
{
    /**
     * 订单回调
     * @param Request $request
     * @return false|string
     * @author zhangzhen
     * @data 2021/6/5 8:41 下午
     */
    public function order(Request $request)
    {
        \Log::info("[饿了么]-[订单回调]，全部参数", $request->all());
        $cmd = $request->get("cmd", "");

        if ($cmd === "order.status.push") {
            $body = json_decode($request->get("body"), true);
            if (is_array($body) && isset($body['status'])) {
                $status = $body['status'] ?? 0;
                if ($status === 5) {
                    return $this->createOrder($body['order_id']);
                } elseif ($status === 10) {
                    return $this->cancelOrder($body['order_id']);
                }
            }
        }

        \Log::info("[饿了么]-[订单回调]，错误请求");
        return $this->res("order.status.success");
    }

    public function cancelOrder($order_id)
    {
        if ($order = Order::query()->where("order_id", $order_id)->first()) {
            $order = Order::query()->where('order_id', $order_id)->first();
            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-开始");

            if (!$order) {
                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-订单不存在");
                // \Log::info('[订单-饿了么接口取消订单]-订单未找到', ['请求参数' => $request->all()]);
                return $this->error("订单不存在");
            }

            $ps = $order->ps;

            if ($order->status == 99) {
                // 已经是取消状态
                return $this->success();
            } elseif ($order->status == 80) {
                // 异常状态
                return $this->success();
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
                                    "description" => "（饿了么）取消美团跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'mt_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【美团】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "美团",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                        $logs = [
                            "des" => "【饿了么接口取消订单】取消美团订单返回失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("饿了么接口取消订单，取消美团订单返回失败", $logs);
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
                                    "description" => "（饿了么）取消蜂鸟跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'fn_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【蜂鸟】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "蜂鸟",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                        $logs = [
                            "des" => "【饿了么接口取消订单】取消蜂鸟订单返回失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("饿了么接口取消订单，取消蜂鸟订单返回失败", $logs);
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
                                    "description" => "（饿了么）取消闪送跑腿订单：" . $order->order_id,
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
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
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
                                    "des" => "（饿了么）取消【闪送】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "闪送",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                        $logs = [
                            "des" => "【饿了么接口取消订单】取消蜂鸟订单返回失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("饿了么接口取消订单，取消闪送订单返回失败", $logs);
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
                                    "description" => "（饿了么）取消美全达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'mqd_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【美全达】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "美全达",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                        $logs = [
                            "des" => "【饿了么接口取消订单】取消美全达订单返回失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("饿了么接口取消订单，取消美全达订单返回失败", $logs);
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
                                    "description" => "（饿了么）取消达达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'dd_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【达达】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "达达",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消美全达订单返回失败", [$result]);
                        $logs = [
                            "des" => "【饿了么接口取消订单】取消达达订单返回失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("饿了么接口取消订单，取消达达订单返回失败", $logs);
                    }
                }
                return $this->res("order.status.success");
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
                            "des" => "（饿了么）取消【美团】跑腿订单"
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
                            "des" => "（饿了么）取消【蜂鸟】跑腿订单"
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
                            "des" => "（饿了么）取消【闪送】跑腿订单"
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
                            "des" => "（饿了么）取消【美全达】跑腿订单"
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
                            "des" => "（饿了么）取消【达达】跑腿订单"
                        ]);
                    }
                }
                return $this->res("order.status.success");
            } else {
                // 状态小于20，属于未发单，直接操作取消
                if ($order->status < 0) {
                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-[订单状态：{$order->status}]-订单状态小于0");
                    $order->status = -10;
                } else {
                    $order->status = 99;
                }
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "（饿了么）取消跑腿订单"
                ]);
                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-未配送");
                return $this->res("order.status.success");
            }
        }
    }

    /**
     * 创建订单
     * @param $order_id
     * @return false|mixed|string
     * @author zhangzhen
     * @data 2021/6/6 8:46 下午
     */
    public function createOrder($order_id)
    {
        $ele = app("ele");
        $order_request = $ele->orderInfo($order_id);
        if (!empty($order_request) && isset($order_request['body']['data']) && !empty($order_request['body']['data'])) {
            // 订单数组
            $order = $order_request['body']['data'];
            // return $order;
            // 订单ID
            $order_id = $order['order']['order_id'];
            // 订单状态
            $status = $order['order']['status'];
            // 饿了么门店ID
            $ele_shop_id = $order['shop']['id'];
            // 订单类型（1 即时单，2 预约单）
            $order_type = $order['order']['send_immediately'];

            // 判断是否是接单状态
            if ($status !== 5) {
                Log::info("【饿了么-推送已确认订单】（{$order_id}）：订单状态不是5，状态：{$status}");
                return $this->error("不是接单状态");
            }

            // 寻找门店
            if (!$shop = Shop::query()->where("ele_shop_id", $ele_shop_id)->first()) {
                Log::info("【饿了么-推送已确认订单】（{$order_id}）：门店不存在，门店ID：{$ele_shop_id}");
                return $this->error("门店不存在");
            }

            $pick_type = $order['order']['business_type'];
            if ($pick_type == 1) {
                // 到店自取订单，不创建订单，返回成功
                return $this->res('order.get.success');
            }
            // 重量，商品列表里面有字段累加就行，但是数据中没个重量都是 1，好像有问题，先写成1
            $weight = 2;
            // 送达时间
            $delivery_time = 0;
            if ($order_type === 2) {
                $delivery_time = $order['order']['send_time'];
            }
            // 创建订单数组
            $order_pt = [
                'delivery_id' => $order_id,
                'user_id' => $shop->user_id,
                'order_id' => $order_id,
                'shop_id' => $shop->id,
                'delivery_service_code' => "4011",
                'receiver_name' => empty($order['user']['name']) ? "无名客人" : $order['user']['name'],
                'receiver_address' => $order['user']['address'],
                'receiver_phone' => str_replace(',', '_', $order['user']['phone']),
                'receiver_lng' => $order['user']['coord_amap']['longitude'],
                'receiver_lat' => $order['user']['coord_amap']['latitude'],
                'coordinate_type' => 0,
                'goods_value' => $order['order']['total_fee'] / 100,
                'goods_weight' => $weight,
                'day_seq' => $order['order']['order_index'],
                'platform' => 2,
                'type' => 21,
                'status' => $status,
                'order_type' => $order_type
            ];

            // 判断是否预约单
            if ($delivery_time > 0) {
                $order_pt['status'] = 3;
                $order_pt['order_type'] = 1;
                $order_pt['expected_pickup_time'] = $delivery_time - 3600;
                $order_pt['expected_delivery_time'] = $delivery_time;
            }

            $order = new Order($order_pt);
            // 保存订单
            if ($order->save()) {
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "（饿了么）自动创建跑腿订单：{$order_id}"
                ]);
                Log::info("【饿了么-推送已确认订单】（{$order_id}）：跑腿订单创建完毕");
                if ($order_type === 2) {
                    $qu = 2400;
                    if ($order->distance <= 2) {
                        $qu = 1800;
                    }

                    dispatch(new PushDeliveryOrder($order, ($order->expected_delivery_time - time() - $qu)));
                    Log::info("【饿了么-推送已确认订单】（{$order_id}）：饿了么创建预约订单成功");

                    $ding_notice = app("ding");
                    $logs = [
                        "des" => "接到饿了么预订单：" . $qu,
                        "datetime" => date("Y-m-d H:i:s"),
                        "order_id" => $order->order_id,
                        "status" => $order->status,
                        "ps" => $order->ps
                    ];
                    $ding_notice->sendMarkdownMsgArray("接到饿了么预订单", $logs);
                } else {
                    Log::info("【饿了么-推送已确认订单】（{$order_id}）：派单单成功");
                    $order->send_at = date("Y-m-d H:i:s");
                    $order->status = 8;
                    $order->save();
                    dispatch(new CreateMtOrder($order, config("ps.order_delay_ttl")));
                }
                return $this->res('order.get.success');
            }
        }
    }

    public function auth(Request $request)
    {
        \Log::info("[饿了么]-[授权回调]，全部参数", $request->all());
    }

    public function res($cmd)
    {
        $data = [
            'body' => json_encode([
                'errno' => 0,
                'error' => 'success'
            ]),
            'cmd' => $cmd,
            'source' => config("ps.ele.app_key"),
            'ticket' => Tool::ticket(),
            'timestamp' => time(),
            'version' => 3
        ];

        $data['sign'] = Tool::getSign($data, config("ele.secret"));

        return json_encode($data);
    }
}
