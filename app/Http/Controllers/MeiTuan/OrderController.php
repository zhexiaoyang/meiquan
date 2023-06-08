<?php

namespace App\Http\Controllers\MeiTuan;

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

class OrderController
{
    use RiderOrderCancel;

    public function status(Request $request)
    {
        // 接收参数
        $status = $request->get('status', '');
        $delivery_id = $request->get('delivery_id', 0);
        $data = $request->only(['courier_name', 'courier_phone', 'cancel_reason_id', 'cancel_reason','status']);
        // 定义日志格式
        $log_prefix = "[美团跑腿回调-订单|订单号:{$delivery_id}]-";
        Log::info($log_prefix . '全部参数', $data);
        $dd = app("ding");
        // 查询订单
        if (($order = Order::where('delivery_id', $delivery_id)->first()) && in_array($status, [0, 20, 30, 50, 99])) {

            $log_prefix = "[美团跑腿回调-订单|订单号:{$delivery_id}|订单状态:{$order->status}|请求状态:{$status}]-";
            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode(['code' => 0]);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode(['code' => 0]);
            }
            // 经度
            $longitude = '';
            // 纬度
            $latitude = '';

            if (in_array($status, [20, 30])) {
                $meituan_app = app("meituan");
                $position = $meituan_app->riderLocation($order->order_id, $order->peisong_id);
                Log::info("美团跑腿配送员坐标-获取|order_id:{$delivery_id}，status:{$status}", [$position]);
                if (isset($position['data']['lng'])) {
                    $longitude = $position['data']['lng'] / 1000000;
                    $latitude = $position['data']['lat'] / 1000000;
                    Log::info("美团跑腿配送员坐标|order_id:{$delivery_id}，status:{$status}", ['lng' => $longitude, 'lat' => $latitude]);
                } else {
                    $shop = Shop::select('shop_lng', 'shop_lat')->find($order->shop_id);
                    $locations = rider_location($shop->shop_lng, $shop->shop_lat);
                    $longitude = $locations['lng'];
                    $latitude = $locations['lat'];
                    Log::info("美团跑腿配送员坐标-没有|order_id:{$delivery_id}，status:{$status}", ['lng' => $longitude, 'lat' => $latitude]);
                }
            }

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是【美团】发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 1)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【美团】发起取消-开始');
                // $logs = [
                //     "des" => "【美团订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【美团】发起取消-开始",
                //     "id" => $order->id,
                //     "order_id" => $order->order_id
                // ];
                // $dd->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] !== 0) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【美团】发起取消-失败');
                    // $logs = [
                    //     "des" => "【美团订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【美团】发起取消-失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】美团取消订单失败", $logs);
                    return json_encode(['code' => 100]);
                }
                OrderLog::create([
                    'ps' => 1,
                    'order_id' => $order->id,
                    'des' => '取消【美团】跑腿订单',
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【美团】发起取消-成功');
                return json_encode(['code' => 0]);
            }

            // 判断状态逻辑
            // 美团跑腿状态【0：待调度，20：已接单，30：已取货，50：已送达，99：已取消】
            // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
            if ($status == 0) {
                // 待调度【待接单】
                // 判断订单状态
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '待接单回调(待接单)，订单状态不正确，不能操作待接单');
                    // $logs = [
                    //     "des" => "【美团订单回调】待接单回调(待接单)，订单状态不正确，不能操作待接单",
                    //     "status" => $order->status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】不能操作待接单", $logs);
                    return json_encode(['code' => 0]);
                }
                // $order->status = 30;
                // $order->mt_status = 30;
                // $order->save();
                Log::info($log_prefix . '待接单');
                return json_encode(['code' => 0]);
            }

            if ($status == 20) {
                $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                if (!$jiedan_lock->get()) {
                    // 获取锁定5秒...
                    $logs = [
                        "des" => "【美团接单】派单后接单了",
                        "status" => $order->status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【派单后接单了】", $logs);
                    sleep(1);
                }
                // 已接单
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调(已接单)，订单状态不正确，不能操作接单');
                    // $logs = [
                    //     "des" => "【美团订单回调】接单回调(已接单)，订单状态不正确，不能操作接单",
                    //     "status" => $order->status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】不能操作接单", $logs);
                    return json_encode(['code' => 0]);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    Log::info($log_prefix . '设置锁失败');
                    // $logs = [
                    //     "des" => "【美团订单回调】设置锁失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return json_encode(['code' => 100]);
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->fn_status > 30) || ($order->ss_status > 30) || ($order->dd_status > 30) || ($order->mqd_status > 30)) {
                    // $logs = [
                    //     "des" => "【美团订单回调】美团接单，蜂鸟闪送已经接过单了",
                    //     "fn_status" => $order->fn_status,
                    //     "ss_status" => $order->ss_status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】蜂鸟闪送已经接过单了", $logs);
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
                        //     "des" => "【美团订单回调】蜂鸟待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("【ERROR】蜂鸟待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 2,
                        'order_id' => $order->id,
                        'des' => '取消【蜂鸟】跑腿订单',
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
                        //     "des" => "【美团订单回调】闪送待接单取消失败",
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
                        //     "des" => "【达达订单回调】美全达待接单取消失败",
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
                // 取消顺丰订单
                if ($order->sf_status === 20 || $order->sf_status === 30) {
                    if ($order->shipper_type_sf) {
                        $sf = app("shunfengservice");
                    } else {
                        $sf = app("shunfeng");
                    }
                    $result = $sf->cancelOrder($order);
                    if ($result['error_code'] != 0) {
                        Log::info($log_prefix . '顺丰待接单取消失败');
                        // $logs = [
                        //     "des" => "【UU订单回调】顺丰待接单取消失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dingding->sendMarkdownMsgArray("【ERROR】顺丰待接单取消失败", $logs);
                    } else {
                        OrderLog::create([
                            'ps' => 7,
                            'order_id' => $order->id,
                            'des' => '取消【顺丰】跑腿订单',
                        ]);
                        Log::info($log_prefix . '取消顺丰待接单订单成功');
                    }
                }
                // 取消众包跑腿
                if ($order->zb_status === 20 || $order->zb_status === 30) {
                    $this->cancelRiderOrderMeiTuanZhongBao($order, 4);
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $data, $longitude, $latitude) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 1,
                            'money' => $order->money_mt,
                            'profit' => 0,
                            'status' => 50,
                            'mt_status' => 50,
                            'fn_status' => $order->fn_status < 20 ?: 7,
                            'ss_status' => $order->ss_status < 20 ?: 7,
                            'mqd_status' => $order->mqd_status < 20 ?: 7,
                            'dd_status' => $order->dd_status < 20 ?: 7,
                            'uu_status' => $order->uu_status < 20 ?: 7,
                            'sf_status' => $order->sf_status < 20 ?: 7,
                            'peisong_id' => $order->mt_order_id,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'courier_name' => $data['courier_name'] ?? '',
                            'courier_phone' => $data['courier_phone'] ?? '',
                            'courier_lng' => $longitude,
                            'courier_lat' => $latitude,
                            'pay_status' => 1,
                            'pay_at' => date("Y-m-d H:i:s"),
                        ]);
                        // 查找扣款用户，为了记录余额日志
                        $current_user = DB::table('users')->find($order->user_id);
                        // 减去用户配送费
                        DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_mt);
                        // 用户余额日志
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money_mt,
                            "type" => 2,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money - $order->money_mt),
                            "description" => "美团跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // DB::table("user_money_balances")->insert();
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 1,
                            "order_id" => $order->id,
                            "des" => "【美团】跑腿，待取货",
                            'name' => $data['courier_name'] ?? '',
                            'phone' => $data['courier_phone'] ?? '',
                        ]);
                    });
                    Log::info($log_prefix . "美团接单，更改信息成功，扣款成功。扣款：{$order->money_mt}");
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    Log::info($log_prefix . '更改信息、扣款事务提交失败', $message);
                    $logs = [
                        "des" => "【美团订单回调】更改信息、扣款失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【ERROR】更改信息、扣款失败", $logs);
                    return json_encode(['code' => 100]);
                }
                // 同步美团外卖配送信息
                $order = Order::where('delivery_id', $delivery_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode(['code' => 0]);
            } elseif ($status == 30) {
                // 已取货【配送中】
                $order->status = 60;
                $order->mt_status = 60;
                $order->take_at = date("Y-m-d H:i:s");
                $order->courier_name = $data['courier_name'] ?? '';
                $order->courier_phone = $data['courier_phone'] ?? '';
                $order->courier_lng = $longitude;
                $order->courier_lat = $latitude;
                $order->cancel_reason_id = $data['cancel_reason_id'] ?? 0;
                $order->cancel_reason = $data['cancel_reason'] ?? '';
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 1,
                    "order_id" => $order->id,
                    "des" => "【美团】跑腿，配送中",
                    'name' => $data['courier_name'] ?? '',
                    'phone' => $data['courier_phone'] ?? '',
                ]);
                Log::info($log_prefix . '配送中，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                return json_encode(['code' => 0]);
            } elseif ($status == 50) {
                $shop = Shop::select('id', 'running_add')->find($order->shop_id);
                // 已送达【已完成】
                $order->profit = $shop->running_add;
                $order->add_money = $shop->running_add;
                $order->status = 70;
                $order->mt_status = 70;
                $order->over_at = date("Y-m-d H:i:s");
                $order->courier_name = $data['courier_name'] ?? '';
                $order->courier_phone = $data['courier_phone'] ?? '';
                $order->courier_lng = $order->receiver_lng;
                $order->courier_lat = $order->receiver_lat;
                $order->cancel_reason_id = $data['cancel_reason_id'] ?? 0;
                $order->cancel_reason = $data['cancel_reason'] ?? '';
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 1,
                    "order_id" => $order->id,
                    "des" => "【美团】跑腿，已送达",
                    'name' => $data['courier_name'] ?? '',
                    'phone' => $data['courier_phone'] ?? '',
                ]);
                Log::info($log_prefix . '已完成，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                return json_encode(['code' => 0]);
            } elseif ($status == 99) {
                // 已取消
                if ($order->status >= 20 && $order->status < 70 && $order->ps == 1) {
                    try {
                        DB::transaction(function () use ($order, $data, $log_prefix) {
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
                                    "description" => "取消美团跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                                Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            }
                            // Order::where("id", $order->id)->update([
                            //     'status' => 99,
                            //     'mt_status' => 99,
                            //     'courier_name' => $data['courier_name'] ?? '',
                            //     'courier_phone' => $data['courier_phone'] ?? '',
                            // ]);

                            $update_data = [
                                'courier_name' => $data['courier_name'] ?? '',
                                'courier_phone' => $data['courier_phone'] ?? '',
                                'mt_status' => 99
                            ];
                            if (in_array(in_array($order->zb_status, [0,1,3,7,80,99]) && $order->fn_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->uu_status, [0,1,3,7,80,99])) {
                                $update_data = [
                                    'courier_name' => $data['courier_name'] ?? '',
                                    'courier_phone' => $data['courier_phone'] ?? '',
                                    'status' => 99,
                                    'mt_status' => 99
                                ];
                            }
                            Order::where("id", $order->id)->update($update_data);

                            OrderLog::create([
                                'ps' => 1,
                                'order_id' => $order->id,
                                'des' => '【美团】跑腿，发起取消配送',
                                'name' => $data['courier_name'] ?? '',
                                'phone' => $data['courier_phone'] ?? '',
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        Log::info($log_prefix . '接口取消订单，将钱返回给用户失败', $message);
                        $logs = [
                            "des" => "【美团订单回调】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
                        return json_encode(['code' => 100]);
                    }
                    Log::info($log_prefix . '接口取消订单成功');
                } else {
                    Log::info($log_prefix . "接口取消订单，状态不正确。状态(status)：{$order->status}");
                }
                return json_encode(['code' => 0]);
            }
        }
        return json_encode(['code' => 0]);
    }

    public function exception(Request $request)
    {
        $res = ['code' => 1];
        $delivery_id = $request->get('delivery_id', 0);
        $data = $request->only(['exception_id', 'exception_code', 'exception_descr', 'exception_time', 'courier_name', 'courier_phone']);
        if ($order = Order::where('delivery_id', $delivery_id)->first()) {
            $order->exception_id = $data['exception_id'];
            $order->exception_code = $data['exception_code'];
            $order->exception_descr = $data['exception_descr'];
            $order->exception_time = $data['exception_time'];
            $order->courier_name = $data['courier_name'];
            $order->courier_phone = $data['courier_phone'];
            if ($order->save()) {
                $res = ['code' => 0];
            }
        }
        \Log::info('订单异常回调', ['request' => $request, 'response' => $res]);
        return json_encode($res);
    }
}
