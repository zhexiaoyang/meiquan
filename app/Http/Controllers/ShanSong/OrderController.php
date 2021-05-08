<?php


namespace App\Http\Controllers\ShanSong;


use App\Jobs\MtLogisticsSync;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OrderController
{
    public function status(Request $request)
    {
        $res = ['status' => 200, 'msg' => '', 'data' => ''];
        // 接收全部参数
        $data = $request->all();
        if (empty($data)) {
            return $res;
        }
        // 商家订单号
        $ss_order_id = $data['issOrderNo'] ?? '';
        $order_id = $data['orderNo'] ?? '';
        // 状态： 1：订单支付，派单中，2：配送员接单，待取件，3：配送员就位，已到店，4：配送员取货，配送中，5：配送员送件完成，已完成
        $status = $data['status'] ?? '';
        // 配送员姓名
        $name = $data['courier']['name'] ?? '';
        // 配送员手机号
        $phone = $data['courier']['mobile'] ?? '';
        // 配送员经度
        $longitude = $data['courier']['longitude'] ?? '';
        // 配送员纬度
        $latitude = $data['courier']['latitude'] ?? '';

        // 定义日志格式
        $log_prefix = "[闪送跑腿回调-订单|订单号:{$order_id}]-";
        Log::info($log_prefix . '全部参数', $data);
        $dd = app("ding");

        // 查找订单
        if ($order = Order::where('order_id', $order_id)->first()) {
            $log_prefix = "[闪送跑腿回调-订单|订单号:{$order_id}|订单状态:{$order->status}|请求状态:{$status}]-";

            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是【闪送】发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 3)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-开始');
                $logs = [
                    "des" => "【闪送订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-开始",
                    "id" => $order->id,
                    "order_id" => $order->order_id
                ];
                $dd->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] != 200) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-失败');
                    $logs = [
                        "des" => "【闪送订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】闪送取消订单失败", $logs);
                    return ['status' => 0, 'msg' => 'err', 'data' => ''];
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 3,
                    "order_id" => $order->id,
                    "des" => "取消【闪送】跑腿订单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-成功');
                return json_encode($res);
            }

            // 闪送跑腿状态【20：派单中，30：取货中，40：闪送中，50：已完成，60：已取消】
            // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
            if ($status == 20) {
                // 派单中
                // 判断订单状态
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '待接单回调(待接单)，订单状态不正确，不能操作待接单');
                    // $logs = [
                    //     "des" => "【闪送订单回调】待接单回调(待接单)，订单状态不正确，不能操作待接单",
                    //     "status" => $order->status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】不能操作待接单", $logs);
                    return json_encode($res);
                }
                $order->status = 30;
                $order->ss_status = 30;
                $order->save();
                Log::info($log_prefix . '待接单');
                return json_encode($res);

            } elseif ($status == 30) {
                // 取货中
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调，订单状态不正确，不能操作接单');
                    $logs = [
                        "des" => "【闪送订单回调】接单回调，订单状态不正确，不能操作接单",
                        "mq_ps" => $order->ps,
                        "mq_status" => $order->status,
                        "ss_status" => $status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】不能操作接单", $logs);
                    return json_encode($res);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    Log::info($log_prefix . '设置锁失败');
                    $logs = [
                        "des" => "【闪送订单回调】设置锁失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return ['status' => 0, 'msg' => 'err', 'data' => ''];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30)) {
                    $logs = [
                        "des" => "【闪送订单回调】闪送接单，美团蜂鸟已经接过单了",
                        "mt_status" => $order->mt_status,
                        "ss_status" => $order->ss_status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】美团蜂鸟已经接过单了", $logs);
                }
                // 取消美团订单
                if ($order->mt_status === 20 || $order->mt_status === 30) {
                    $meituan = app("meituan");
                    $result = $meituan->delete([
                        'delivery_id' => $order->delivery_id,
                        'mt_peisong_id' => $order->mt_order_id,
                        'cancel_reason_id' => 399,
                        'cancel_reason' => '其他原因',
                    ]);
                    if ($result['code'] !== 0) {
                        $logs = [
                            "des" => "【闪送订单回调】美团待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("【ERROR】美团待接单取消失败", $logs);
                    }
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 1,
                        "order_id" => $order->id,
                        "des" => "取消【美团】跑腿订单",
                        'name' => $name,
                        'phone' => $phone,
                    ]);
                    Log::info($log_prefix . '取消美团待接单订单成功');
                }
                // 取消蜂鸟订单
                if ($order->fn_status === 20 || $order->fn_status === 30) {
                    $fengniao = app("fengniao");
                    $result = $fengniao->cancelOrder([
                        'partner_order_code' => $order->order_id,
                        'order_cancel_reason_code' => 2,
                        'order_cancel_code' => 9,
                        'order_cancel_time' => time() * 1000,
                    ]);
                    if ($result['code'] != 200) {
                        $logs = [
                            "des" => "【闪送订单回调】蜂鸟待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("【ERROR】蜂鸟待接单取消失败", $logs);
                    }
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 2,
                        "order_id" => $order->id,
                        "des" => "取消【蜂鸟】跑腿订单",
                        'name' => $name,
                        'phone' => $phone,
                    ]);
                    Log::info($log_prefix . '取消蜂鸟待接单订单成功');
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 3,
                            'money' => $order->money_ss,
                            'status' => 50,
                            'ss_status' => 50,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'fn_status' => $order->ss_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->ss_order_id,
                            'courier_name' => $name,
                            'courier_phone' => $phone,
                        ]);
                        // 查找扣款用户，为了记录余额日志
                        $current_user = DB::table('users')->find($order->user_id);
                        // 减去用户配送费
                        DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_ss);
                        // 用户余额日志
                        // DB::table("user_money_balances")->insert();
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money_ss,
                            "type" => 1,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money - $order->money_ss),
                            "description" => "闪送跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 3,
                            "order_id" => $order->id,
                            "des" => "【闪送】跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    Log::info($log_prefix . "闪送接单，更改信息成功，扣款成功。扣款：{$order->money_ss}");
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    Log::info($log_prefix . '更改信息、扣款事务提交失败', $message);
                    $logs = [
                        "des" => "【闪送订单回调】更改信息、扣款失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】更改信息、扣款失败", $logs);
                    return ['status' => 0, 'msg' => 'err', 'data' => ''];
                }
                // 同步美团外卖配送信息
                $order = Order::where('order_id', $order_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);

            } elseif ($status == 40) {
                // 送货中
                $order->status = 60;
                $order->ss_status = 60;
                $order->take_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 3,
                    "order_id" => $order->id,
                    "des" => "【闪送】跑腿，配送中",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 50) {
                $order->status = 70;
                $order->ss_status = 70;
                $order->over_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 3,
                    "order_id" => $order->id,
                    "des" => "【闪送】跑腿，已送达",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 60) {
                if ($order->status > 20 && $order->status < 70 && $order->ps == 3) {
                    try {
                        DB::transaction(function () use ($order, $name, $phone, $log_prefix) {
                            if ($order->status == 50 || $order->status == 60) {
                                // 查询当前用户，做余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                // DB::table("user_money_balances")->insert();
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 2,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "取消闪送跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_ss);
                                Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            }
                            Order::where("id", $order->id)->update([
                                'status' => 99,
                                'ss_status' => 99,
                                'courier_name' => $name,
                                'courier_phone' => $phone,
                            ]);
                            OrderLog::create([
                                'ps' => 3,
                                'order_id' => $order->id,
                                'des' => '【闪送】跑腿，发起取消配送',
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        Log::info($log_prefix . '取消订单，将钱返回给用户失败', $message);
                        $logs = [
                            "des" => "【闪送订单回调】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("闪送接口取消订单将钱返回给用户失败", $logs);
                        return json_encode(['code' => 100]);
                    }
                    Log::info($log_prefix . '接口取消订单成功');
                } else {
                    Log::info($log_prefix . "取消订单，状态不正确。状态(status)：{$order->status}");
                }
                return json_encode($res);
            }
        }
        return json_encode($res);
    }
}
