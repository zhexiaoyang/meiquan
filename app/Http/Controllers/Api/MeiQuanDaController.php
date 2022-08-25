<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\MtLogisticsSync;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MeiQuanDaController extends Controller
{
    public function order_status(Request $request)
    {
        $res = ['code' => 100, 'message' => '', 'data' => []];
        // 接收全部参数
        $data = $request->all();
        if (empty($data)) {
            return json_encode($res);
        }
        // 商家订单号
        $trade_no = $data['trade_no'] ?? '';
        // 状态： 4 取单中(已接单，已抢单) 5 送单中(已取单) 6 送达订单 7 撤销订单
        $status = $data['state'] ?? '';
        // 配送员姓名
        $name = $data['courier_name'] ?? '';
        // 配送员手机号
        $phone = $data['courier_tel'] ?? '';
        $courier_tag = explode(",", $data['courier_tag'] ?? "");
        // 配送员经度
        $longitude = $courier_tag[0] ?? '';
        // 配送员纬度
        $latitude = $courier_tag[1] ?? '';
        if (in_array($status, [4,5])) {
            // 美全达跑腿状态【4：取单中(已接单，已抢单)，5：送单中(已取单)，6：送达订单 ，7：撤销订单】
            Log::info("美全达配送员坐标|order_id:{$trade_no}，status:{$status}", ['lng' => $longitude, 'lat' => $latitude]);
        }

        // 定义日志格式
        $log_prefix = "[美全达跑腿回调-订单|订单号:{$trade_no}]-";
        Log::info($log_prefix . '全部参数', $data);
        $dd = app("ding");

        // 查找订单
        if ($order = Order::where('mqd_order_id', $trade_no)->first()) {
            $order_id = $order->order_id;
            $log_prefix = "[美全达跑腿回调-订单|订单号:{$order_id}|订单状态:{$order->status}|请求状态:{$status}]-";

            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是【美全达】发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 4) && ($status != 7)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【美全达】发起取消-开始');
                // $logs = [
                //     "des" => "【美全达订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【美全达】发起取消-开始",
                //     "id" => $order->id,
                //     "order_id" => $order->order_id
                // ];
                // $dd->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $meiquanda = app("meiquanda");
                $result = $meiquanda->repealOrder($order->mqd_order_id);
                if ($result['code'] != 100) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【美全达】发起取消-失败');
                    // $logs = [
                    //     "des" => "【美全达订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【美全达】发起取消-失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】美全达取消订单失败", $logs);
                    return ['status' => 0, 'msg' => 'err', 'data' => ''];
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 4,
                    "order_id" => $order->id,
                    "des" => "取消【美全达】跑腿订单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【美全达】发起取消-成功');
                return json_encode($res);
            }

            // 美全达跑腿状态【4：取单中(已接单，已抢单)，5：送单中(已取单)，6：送达订单 ，7：撤销订单】
            // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
            if ($status == 4) {
                $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                if (!$jiedan_lock->get()) {
                    // 获取锁定5秒...
                    $logs = [
                        "des" => "【美全达接单】派单后接单了",
                        "status" => $order->status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【派单后接单了】", $logs);
                    sleep(1);
                }
                // 取货中
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调，订单状态不正确，不能操作接单');
                    // $logs = [
                    //     "des" => "【美全达订单回调】接单回调，订单状态不正确，不能操作接单",
                    //     "date" => date("Y-m-d H:i:s"),
                    //     "mq_ps" => $order->ps,
                    //     "mq_status" => $order->status,
                    //     "mqd_status" => $status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】不能操作接单", $logs);
                    return json_encode($res);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    Log::info($log_prefix . '设置锁失败');
                    // $logs = [
                    //     "des" => "【美全达订单回调】设置锁失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return ['status' => 0, 'msg' => 'err', 'data' => ''];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30) || ($order->ss_status > 30) || ($order->dd_status > 30)) {
                    // $logs = [
                    //     "des" => "【美全达订单回调】美全达接单，其它平台已经接过单了",
                    //     "mt_status" => $order->mt_status,
                    //     "mqd_status" => $order->mqd_status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】美团蜂鸟已经接过单了", $logs);
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
                        //     "des" => "【美全达订单回调】美团待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】美团待接单取消失败", $logs);
                    }
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 1,
                        "order_id" => $order->id,
                        "des" => "取消【美团】跑腿订单",
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
                        // $logs = [
                        //     "des" => "【美全达订单回调】蜂鸟待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】蜂鸟待接单取消失败", $logs);
                    }
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 2,
                        "order_id" => $order->id,
                        "des" => "取消【蜂鸟】跑腿订单",
                    ]);
                    Log::info($log_prefix . '取消蜂鸟待接单订单成功');
                }
                // 取消闪送订单
                if ($order->ss_status === 20 || $order->ss_status === 30) {
                    if ($order->shipper_type_ss) {
                        $shansong = new ShanSongService(config('ps.shansongservice'));
                    } else {
                        $shansong = app("shansong");
                    }
                    $result = $shansong->cancelOrder($order->ss_order_id);
                    if ($result['status'] != 200) {
                        // $logs = [
                        //     "des" => "【美全达订单回调】闪送待接单取消失败",
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
                // 取消达达订单
                if ($order->dd_status === 20 || $order->dd_status === 30) {
                    if ($order->shipper_type_dd) {
                        $config = config('ps.dada');
                        $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                        $dada = new DaDaService($config);
                    } else {
                        $dada = app("dada");
                    }
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] != 0) {
                        // $logs = [
                        //     "des" => "【美全达订单回调】达达待接单取消失败",
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
                        //     "des" => "【美全达订单回调】UU待接单取消失败",
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
                // 取消顺丰订单
                if ($order->sf_status === 20 || $order->sf_status === 30) {
                    if ($order->shipper_type_sf) {
                        $sf = app("shunfengservice");
                    } else {
                        $sf = app("shunfeng");
                    }
                    $result = $sf->cancelOrder($order);
                    if ($result['error_code'] != 0) {
                        $logs = [
                            "des" => "【UU订单回调】顺丰待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("【ERROR】顺丰待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 7,
                        'order_id' => $order->id,
                        'des' => '取消【顺丰】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消顺丰待接单订单成功');
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone, $longitude, $latitude) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 4,
                            'money' => $order->money_mqd,
                            'status' => 50,
                            'mqd_status' => 50,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'fn_status' => $order->fn_status < 20 ?: 7,
                            'ss_status' => $order->ss_status < 20 ?: 7,
                            'dd_status' => $order->dd_status < 20 ?: 7,
                            'uu_status' => $order->uu_status < 20 ?: 7,
                            'sf_status' => $order->sf_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->mqd_order_id,
                            'courier_name' => $name,
                            'courier_phone' => $phone,
                            'courier_lng' => $longitude,
                            'courier_lat' => $latitude,
                            'pay_status' => 1,
                            'pay_at' => date("Y-m-d H:i:s"),
                        ]);
                        // 查找扣款用户，为了记录余额日志
                        $current_user = DB::table('users')->find($order->user_id);
                        // 减去用户配送费
                        DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_mqd);
                        // 用户余额日志
                        // DB::table("user_money_balances")->insert();
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money_mqd,
                            "type" => 2,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money - $order->money_mqd),
                            "description" => "美全达跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 4,
                            "order_id" => $order->id,
                            "des" => "【美全达】跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    Log::info($log_prefix . "美全达接单，更改信息成功，扣款成功。扣款：{$order->money_mqd}");
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    Log::info($log_prefix . '更改信息、扣款事务提交失败', $message);
                    $logs = [
                        "des" => "【美全达订单回调】更改信息、扣款失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】更改信息、扣款失败", $logs);
                    return ['code' => 100, 'msg' => 'success', 'data' => ''];
                }
                // 同步美团外卖配送信息
                $order = Order::where('order_id', $order_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 5) {
                // 美全达跑腿状态【4：取单中(已接单，已抢单)，5：送单中(已取单)，6：送达订单 ，7：撤销订单】
                // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
                if ($order->status < 60) {
                    // 送货中
                    $order->status = 60;
                    $order->mqd_status = 60;
                    $order->take_at = date("Y-m-d H:i:s");
                    $order->courier_name = $name;
                    $order->courier_phone = $phone;
                    $order->courier_lng = $longitude;
                    $order->courier_lat = $latitude;
                    $order->save();
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 4,
                        "order_id" => $order->id,
                        "des" => "【美全达】跑腿，配送中",
                        'name' => $name,
                        'phone' => $phone,
                    ]);
                    dispatch(new MtLogisticsSync($order));
                } else {
                    Log::info($log_prefix . '订单已取货');
                }
                return json_encode($res);
            } elseif ($status == 6) {
                $order->status = 70;
                $order->mqd_status = 70;
                $order->over_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $order->receiver_lng;
                $order->courier_lat = $order->receiver_lat;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 4,
                    "order_id" => $order->id,
                    "des" => "【美全达】跑腿，已送达",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 7) {
                if ($order->status >= 20 && $order->status < 70 ) {
                    if ($order->mqd_status == 99) {
                        Log::info($log_prefix . '接口取消订单-已经是取消状态');
                        return json_encode($res);
                    }
                    try {
                        DB::transaction(function () use ($order, $name, $phone, $log_prefix) {
                            // if (($order->status == 50 || $order->status == 60) && $order->ps == 4) {
                                // 查询当前用户，做余额日志
                                // $current_user = DB::table('users')->find($order->user_id);
                                // DB::table("user_money_balances")->insert();
                                // UserMoneyBalance::create([
                                //     "user_id" => $order->user_id,
                                //     "money" => $order->money,
                                //     "type" => 1,
                                //     "before_money" => $current_user->money,
                                //     "after_money" => ($current_user->money + $order->money),
                                //     "description" => "取消美全达跑腿订单：" . $order->order_id,
                                //     "tid" => $order->id
                                // ]);
                                // 将配送费返回
                                // DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mqd);
                                // Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            // }

                            $update_data = [
                                'mqd_status' => 99
                            ];
                            if (in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->uu_status, [0,1,3,7,80,99])) {
                                $update_data = [
                                    'status' => 99,
                                    'mqd_status' => 99
                                ];
                            }
                            Order::where("id", $order->id)->update($update_data);
                            OrderLog::create([
                                'ps' => 4,
                                'order_id' => $order->id,
                                'des' => '【美全达】跑腿，发起取消配送',
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
                            "des" => "【美全达订单回调】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美全达接口取消订单将钱返回给用户失败", $logs);
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
