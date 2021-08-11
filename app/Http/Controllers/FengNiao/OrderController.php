<?php


namespace App\Http\Controllers\FengNiao;

use App\Jobs\MtLogisticsSync;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OrderController
{
    public function status(Request $request)
    {
        $res = ['code' => 200, 'msg' => '', 'data' => ''];
        // 接收全部参数
        if (!$data_str = $request->get('data', '')) {
            return [];
        }
        $data = json_decode(urldecode($data_str), true);
        if (empty($data)) {
            return [];
        }
        // 商家订单号
        $order_id = $data['partner_order_code'] ?? '';
        // 状态： 1 接单，20 分配骑手，80 骑手到店，2 订单配送中，3 已送达，5 订单异常/拒单
        $status = $data['order_status'] ?? '';
        // 配送员姓名
        $name = $data['carrier_driver_name'] ?? '';
        // 配送员手机号
        $phone = $data['carrier_driver_phone'] ?? '';
        // 错误信息
        $description = $data['description'] ?? '';
        // 错误信息详细
        // $detail_description = $data['detail_description'] ?? '';

        // 定义日志格式
        $log_prefix = "[蜂鸟跑腿回调-订单|订单号:{$order_id}]-";
        Log::info($log_prefix . '全部参数', $data);
        $dd = app("ding");

        // 查找订单
        if ($order = Order::where('order_id', $order_id)->first()) {

            $log_prefix = "[蜂鸟跑腿回调-订单|订单号:{$order_id}|订单状态:{$order->status}|请求状态:{$status}]-";
            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是【蜂鸟】发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 2)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【蜂鸟】发起取消-开始');
                // $logs = [
                //     "des" => "【蜂鸟订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【蜂鸟】发起取消-开始",
                //     "id" => $order->id,
                //     "order_id" => $order->order_id
                // ];
                // $dd->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] != 200) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【蜂鸟】发起取消-失败');
                    // $logs = [
                    //     "des" => "【蜂鸟订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【蜂鸟】发起取消-失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】蜂鸟取消订单失败", $logs);
                    return false;
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 2,
                    "order_id" => $order->id,
                    "des" => "取消【蜂鸟】跑腿订单"
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【蜂鸟】发起取消-成功');
                return json_encode($res);
            }

            // 蜂鸟跑腿状态【1：接单，20：分配骑手，80：骑手到店，2：订单配送中，3：已送达，5：订单异常/拒单，4：已取消】
            // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
            if ($status == 1) {
                // 系统已接单
                // 判断订单状态
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '待接单回调(待接单)，订单状态不正确，不能操作待接单');
                    // $logs = [
                    //     "des" => "【蜂鸟订单回调】待接单回调(待接单)，订单状态不正确，不能操作待接单",
                    //     "status" => $order->status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】不能操作待接单", $logs);
                    return json_encode($res);
                }
                $order->status = 30;
                $order->fn_status = 30;
                $order->save();
                Log::info($log_prefix . '待接单');
                return json_encode($res);
            }
            if ($status == 20) {
                // 已分配骑手
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调，订单状态不正确，不能操作接单');
                    // $logs = [
                    //     "des" => "【蜂鸟订单回调】接单回调，订单状态不正确，不能操作接单",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】不能操作接单", $logs);
                    return json_encode(['status' => 200, 'msg' => '', 'data' => '']);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    Log::info($log_prefix . '设置锁失败');
                    // $logs = [
                    //     "des" => "【蜂鸟订单回调】设置锁失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return false;
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->ss_status > 30) || ($order->dd_status > 30) || ($order->mqd_status > 30)) {
                    // $logs = [
                    //     "des" => "【蜂鸟订单回调】蜂鸟接单，美团闪送已经接过单了",
                    //     "mt_status" => $order->mt_status,
                    //     "ss_status" => $order->ss_status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】美团闪送已经接过单了", $logs);
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
                        // $logs = [
                        //     "des" => "【蜂鸟订单回调】美团待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】美团待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 1,
                        'order_id' => $order->id,
                        'des' => '取消【美团】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消美团待接单订单成功');
                }
                // 取消闪送订单
                if ($order->ss_status === 20 || $order->ss_status === 30) {
                    $shansong = app("shansong");
                    $result = $shansong->cancelOrder($order->ss_order_id);
                    if ($result['status'] != 200) {
                        // $logs = [
                        //     "des" => "【蜂鸟订单回调】闪送待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】闪送待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 3,
                        'order_id' => $order->id,
                        'des' => '取消【闪送】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消闪送待接单订单成功');
                }
                // 取消美全达订单
                if ($order->mqd_status === 20 || $order->mqd_status === 30) {
                    $meiquanda = app("meiquanda");
                    $result = $meiquanda->repealOrder($order->mqd_order_id);
                    if ($result['code'] != 100) {
                        // $logs = [
                        //     "des" => "【蜂鸟订单回调】美全达待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】美全达待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 4,
                        'order_id' => $order->id,
                        'des' => '取消【美全达】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消美全达待接单订单成功');
                }
                // 取消达达订单
                if ($order->dd_status === 20 || $order->dd_status === 30) {
                    $dada = app("dada");
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] != 0) {
                        // $logs = [
                        //     "des" => "【蜂鸟订单回调】达达待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】达达待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '取消【达达】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消达达待接单订单成功');
                }
                // 取消UU订单
                if ($order->uu_status === 20 || $order->uu_status === 30) {
                    $uu = app("uu");
                    $result = $uu->cancelOrder($order);
                    if ($result['return_code'] != 'ok') {
                        // $logs = [
                        //     "des" => "【美团订单回调】UU待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】UU待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 6,
                        'order_id' => $order->id,
                        'des' => '取消【UU跑腿】订单',
                    ]);
                    Log::info($log_prefix . '取消UU待接单订单成功');
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 2,
                            'money' => $order->money_fn,
                            'status' => 50,
                            'fn_status' => 50,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'ss_status' => $order->ss_status < 20 ?: 7,
                            'mqd_status' => $order->mqd_status < 20 ?: 7,
                            'dd_status' => $order->dd_status < 20 ?: 7,
                            'uu_status' => $order->dd_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->fn_order_id,
                            'courier_name' => $name,
                            'courier_phone' => $phone,
                        ]);
                        // 查找扣款用户，为了记录余额日志
                        $current_user = DB::table('users')->find($order->user_id);
                        // 减去用户配送费
                        DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_fn);
                        // 用户余额日志
                        // DB::table("user_money_balances")->insert();
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money_fn,
                            "type" => 2,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money - $order->money_fn),
                            "description" => "蜂鸟跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 2,
                            "order_id" => $order->id,
                            "des" => "【蜂鸟】跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    Log::info($log_prefix . "蜂鸟接单，更改信息成功，扣款成功。扣款：{$order->money_fn}");
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    Log::info($log_prefix . '更改信息、扣款事务提交失败', $message);
                    $logs = [
                        "des" => "【蜂鸟订单回调】更改信息、扣款失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】更改信息、扣款失败", $logs);
                    return false;
                }
                // 同步美团外卖配送信息
                $order = Order::where('order_id', $order_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);

            } elseif ($status == 80) {
                // 骑手已到店
                // $order->status = 50;
                return json_encode($res);

            } elseif ($status == 2) {
                // 配送中
                $order->status = 60;
                $order->fn_status = 60;
                $order->take_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->exception_descr = $description;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 2,
                    "order_id" => $order->id,
                    "des" => "【蜂鸟】跑腿，配送中",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                Log::info($log_prefix . '配送中，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 3) {
                // 已送达
                $order->status = 70;
                $order->fn_status = 70;
                $order->over_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->exception_descr = $description;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 2,
                    "order_id" => $order->id,
                    "des" => "【蜂鸟】跑腿，已送达",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                Log::info($log_prefix . '配送中，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            // } elseif ($status == 5) {
            //     // 异常
            //     $order->status = 80;
            //     $order->fn_status = 80;
            //     $order->courier_name = $name;
            //     $order->courier_phone = $phone;
            //     $order->exception_descr = $description;
            //     $order->save();
            //     return json_encode($res);
            } elseif ($status == 4 || $status == 5) {
                if ($status == 5) {
                    Log::info($log_prefix . '蜂鸟订单异常：' . $description);
                }
                // 取消
                if ($order->status >= 20 && $order->status < 70 && $order->ps == 2) {
                    try {
                        DB::transaction(function () use ($order, $name, $phone, $log_prefix) {
                            if ($order->status == 50 || $order->status == 60) {
                                // 查询当前用户，做余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                // DB::table("user_money_balances")->insert();
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "取消蜂鸟跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_fn);
                                Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            }
                            // Order::where("id", $order->id)->update([
                            //     'status' => 99,
                            //     'fn_status' => 99,
                            //     'courier_name' => $name,
                            //     'courier_phone' => $phone,
                            // ]);

                            $update_data = [
                                'courier_name' => $name,
                                'courier_phone' => $phone,
                                'fn_status' => 99
                            ];
                            if (in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99])) {
                                $update_data = [
                                    'courier_name' => $name,
                                    'courier_phone' => $phone,
                                    'status' => 99,
                                    'fn_status' => 99
                                ];
                            }
                            Order::where("id", $order->id)->update($update_data);

                            OrderLog::create([
                                'ps' => 2,
                                'order_id' => $order->id,
                                'des' => '【蜂鸟】跑腿，发起取消配送',
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
                            "des" => "【蜂鸟订单回调】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("蜂鸟接口取消订单将钱返回给用户失败", $logs);
                        return json_encode(['code' => 100]);
                    }
                    $logs = [
                        "des" => "【蜂鸟发起取消配送】",
                        "id" => $order->id,
                        "order_id" => $order->order_id,
                        "date" => date("Y-m-d H:i:s")
                    ];
                    $dd->sendMarkdownMsgArray("【蜂鸟发起取消配送】", $logs);
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
