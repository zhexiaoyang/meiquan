<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\MtLogisticsSync;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use App\Traits\RiderOrderCancel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UuController extends Controller
{
    use RiderOrderCancel;

    public function order_status(Request $request)
    {
        $res = ['return_code' => 'ok'];

        \Log::info("UU跑腿回调全部参数", $request->all());

        // 接收全部参数
        $data_str = $request->get('data');
        $data = json_decode($data_str, true);
        if (empty($data)) {
            return json_encode($res);
        }

        \Log::info("UU跑腿回调全部参数", $data);

        // return json_encode($res);
        // 商家订单号
        $order_id = $data['origin_id'] ?? '';
        // 订单状态(1下单成功 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消）
        $status = $data['state'] ?? '';
        $state_text = $data['state_text'] ?? '';
        // \Log::info('$status:' . $status);
        // \Log::info('$state_text:' . $state_text);
        // 配送员姓名
        $name = $data['driver_name'] ?? '';
        // 配送员手机号
        $phone = $data['driver_mobile'] ?? '';
        $longitude = '';
        $latitude = '';

        // 定义日志格式
        $log_prefix = "[UU跑腿回调-订单|订单号:{$order_id}]-";
        Log::info($log_prefix . '全部参数', $data);
        $dingding = app("ding");

        // 查找订单
        if ($order = Order::where('order_id', $order_id)->first()) {
            // UU配送员坐标
            // 订单状态(1下单成功 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消）
            if (in_array($status, [3,5])) {
                $uu_app = app("uu");
                $uu_app_res = $uu_app->getOrderInfo($order_id);
                // 配送员坐标
                $driver_lastloc = explode(",", $uu_app_res['driver_lastloc'] ?? "");
                // 配送员经度
                $longitude = $driver_lastloc[0] ?? '';
                // 配送员纬度
                $latitude = $driver_lastloc[1] ?? '';
                Log::info("UU配送员坐标|order_id:{$order_id}，status:{$status}", ['lng' => $longitude, 'lat' => $latitude]);
            }
            // 日志格式
            $log_prefix = "[UU跑腿回调-订单|订单号:{$order_id}|订单状态:{$order->status}|请求状态:{$status}]-";

            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是【UU】发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 6) && $status > 0) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【UU】发起取消-开始');
                $logs = [
                    "des" => "【UU订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【UU】发起取消-开始",
                    "id" => $order->id,
                    "order_id" => $order->order_id
                ];
                $dingding->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] != 'ok') {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【UU】发起取消-失败');
                    $logs = [
                        "des" => "【UU订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【UU】发起取消-失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【ERROR】UU取消订单失败", $logs);
                    return ['status' => 'err'];
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 6,
                    "order_id" => $order->id,
                    "des" => "取消【UU】跑腿订单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【UU】发起取消-成功');
                return json_encode($res);
            }

            // 订单状态(1下单成功 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消）
            if ($status == 3) {
                $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                if (!$jiedan_lock->get()) {
                    // 获取锁定5秒...
                    $logs = [
                        "des" => "【UU接单】派单后接单了",
                        "status" => $order->status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【派单后接单了】", $logs);
                    sleep(1);
                }
                // 取货中
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调，订单状态不正确，不能操作接单');
                    // $logs = [
                    //     "des" => "【UU订单回调】接单回调，订单状态不正确，不能操作接单",
                    //     "date" => date("Y-m-d H:i:s"),
                    //     "mq_ps" => $order->ps,
                    //     "mq_status" => $order->status,
                    //     "uu_status" => $status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dingding->sendMarkdownMsgArray("【ERROR】【UU】不能操作接单", $logs);
                    return json_encode($res);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    Log::info($log_prefix . '设置锁失败');
                    $logs = [
                        "des" => "【UU订单回调】设置锁失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return ['return_code' => 'err'];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30) || ($order->ss_status > 30) || ($order->mqd_status > 30)) {
                    $logs = [
                        "des" => "【UU订单回调】UU接单，其它平台已经接过单了",
                        "mt_status" => $order->mt_status,
                        "mqd_status" => $order->mqd_status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【ERROR】其它平台已经接过单了", $logs);
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
                            "des" => "【UU订单回调】美团待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("【ERROR】美团待接单取消失败", $logs);
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
                        $logs = [
                            "des" => "【UU订单回调】蜂鸟待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("【ERROR】蜂鸟待接单取消失败", $logs);
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
                        $logs = [
                            "des" => "【UU订单回调】闪送待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("【ERROR】闪送待接单取消失败", $logs);
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
                        $logs = [
                            "des" => "【UU订单回调】美全达待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("【ERROR】美全达待接单取消失败", $logs);
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
                    if ($order->shipper_type_dd) {
                        $config = config('ps.dada');
                        $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                        $dada = new DaDaService($config);
                    } else {
                        $dada = app("dada");
                    }
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] != 0) {
                        $logs = [
                            "des" => "【UU订单回调】达达待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("【ERROR】达达待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '取消【达达】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消达达待接单订单成功');
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
                        // $logs = [
                        //     "des" => "【UU订单回调】顺丰待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dingding->sendMarkdownMsgArray("【ERROR】顺丰待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 7,
                        'order_id' => $order->id,
                        'des' => '取消【顺丰】跑腿订单',
                    ]);
                    Log::info($log_prefix . '取消顺丰待接单订单成功');
                }
                // 取消众包跑腿
                if ($order->zb_status === 20 || $order->zb_status === 30) {
                    $this->cancelRiderOrderMeiTuanZhongBao($order, 9);
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone, $longitude, $latitude) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 6,
                            'money' => $order->money_uu,
                            'profit' => 0,
                            'status' => 50,
                            'uu_status' => 50,
                            'dd_status' => $order->dd_status < 20 ?: 7,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'fn_status' => $order->fn_status < 20 ?: 7,
                            'ss_status' => $order->ss_status < 20 ?: 7,
                            'mqd_status' => $order->mqd_status < 20 ?: 7,
                            'sf_status' => $order->sf_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->uu_order_id,
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
                        DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_uu);
                        // 用户余额日志
                        // DB::table("user_money_balances")->insert();
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money_uu,
                            "type" => 2,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money - $order->money_uu),
                            "description" => "UU跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 6,
                            "order_id" => $order->id,
                            "des" => "【UU】跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    Log::info($log_prefix . "UU接单，更改信息成功，扣款成功。扣款：{$order->money_uu}");
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    Log::info($log_prefix . '更改信息、扣款事务提交失败', $message);
                    $logs = [
                        "des" => "【UU订单回调】更改信息、扣款失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【ERROR】更改信息、扣款失败", $logs);
                    return ['code' => 'error'];
                }
                // 同步美团外卖配送信息
                $order = Order::where('delivery_id', $order_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 5) {
                // 订单状态(1下单成功 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消）
                // 送货中
                $order->status = 60;
                $order->uu_status = 60;
                $order->take_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $longitude;
                $order->courier_lat = $latitude;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 6,
                    "order_id" => $order->id,
                    "des" => "【UU】跑腿，配送中",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif (($status == 10) || ($status == 6 && $state_text == '已送达')) {
                // 订单状态(1下单成功 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消）
                if ($order->status != 70) {
                    $shop = Shop::select('id', 'running_add')->find($order->shop_id);
                    // 已送达【已完成】
                    $order->profit = $shop->running_add;
                    $order->add_money = $shop->running_add;
                    $order->status = 70;
                    $order->uu_status = 70;
                    $order->over_at = date("Y-m-d H:i:s");
                    $order->courier_name = $name;
                    $order->courier_phone = $phone;
                    $order->courier_lng = $order->receiver_lng;
                    $order->courier_lat = $order->receiver_lat;
                    $order->save();
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 6,
                        "order_id" => $order->id,
                        "des" => "【UU】跑腿，已送达",
                        'name' => $name,
                        'phone' => $phone,
                    ]);
                    dispatch(new MtLogisticsSync($order));
                }
                return json_encode($res);
            } elseif ($status == -1) {
                if ($order->status >= 20 && $order->status < 70 ) {
                    sleep(1);
                    try {
                        DB::transaction(function () use ($order, $name, $phone, $log_prefix) {
                            if (($order->status == 50 || $order->status == 60) && $order->ps == 6) {
                                // 查询当前用户，做余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                // DB::table("user_money_balances")->insert();
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "取消UU跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_uu);
                                Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            }

                            $update_data = [
                                'uu_status' => 99
                            ];
                            if (in_array(in_array($order->zb_status, [0,1,3,7,80,99]) && $order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99])) {
                                $update_data = [
                                    'status' => 99,
                                    'uu_status' => 99
                                ];
                            }
                            Order::where("id", $order->id)->update($update_data);
                            OrderLog::create([
                                'ps' => 6,
                                'order_id' => $order->id,
                                'des' => '【UU】跑腿，发起取消配送',
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
                            "des" => "【UU订单回调】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("UU接口取消订单将钱返回给用户失败", $logs);
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
