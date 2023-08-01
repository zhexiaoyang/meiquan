<?php

namespace App\Http\Controllers\ShanSong;

use App\Jobs\CreateMtOrder;
use App\Jobs\MtLogisticsSync;
use App\Libraries\DaDaService\DaDaService;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use App\Traits\RiderOrderCancel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Class OrderController
 * @package App\Http\Controllers\ShanSong
 * 闪送订单状态回调-聚合运力
 */
class OrderController
{
    use RiderOrderCancel;

    public $log_prefix = '[闪送订单回调&###]';

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
        // 闪送跑腿状态【20：派单中，30：取货中，40：闪送中，50：已完成，60：已取消】
        $status = $data['status'] ?? '';
        if ($status == 20) {
            // 20：派单中-等待骑手接单
            return $this->pendingOrder($ss_order_id);
        }
        // 配送员姓名
        $name = $data['courier']['name'] ?? '';
        // 配送员手机号
        $phone = $data['courier']['mobile'] ?? '';
        // 闪送员位置经度（百度坐标系）
        $longitude = $data['courier']['longitude'] ?? '';
        // 闪送员位置纬度（百度坐标系）
        $latitude = $data['courier']['latitude'] ?? '';
        $locations = ['lng' => $longitude, 'lat' => $latitude];
        if (in_array($status, [30,40])) {
            if ($longitude && $latitude) {
                $locations = bd2gd($longitude, $latitude);
                Log::info("闪送配送员坐标-转换后|order_id:{$order_id}，status:{$status}", $locations);
            } else {
                Log::info("闪送配送员坐标-未转换|order_id:{$order_id}，status:{$status}", $locations);
            }
        }
        // 取消类型
        $abort_type = $data['abortType'] ?? 0;

        if ($status == 30) {
            // 30：取货中-骑手已接单
            return $this->pickingUp($ss_order_id, $name, $phone, $longitude, $latitude, $locations);
        }

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
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 3) && ($status != 60)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-开始');
                // $logs = [
                //     "des" => "【闪送订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-开始",
                //     "id" => $order->id,
                //     "order_id" => $order->order_id
                // ];
                // $dd->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] != 200) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-失败');
                    // $logs = [
                    //     "des" => "【闪送订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【闪送】发起取消-失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】闪送取消订单失败", $logs);
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
                $before_time = time();
                Log::info($log_prefix . "派单中-睡眠之前：" . date("Y-m-d H:i:s", $before_time));
                sleep(1);
                $after_time = time();
                Log::info($log_prefix . "派单中-睡眠之后：" . date("Y-m-d H:i:s", $after_time));
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
                // $order->status = 30;
                // $order->ss_status = 30;
                // $order->save();
                Log::info($log_prefix . '待接单');
                return json_encode($res);

            } elseif ($status == 30) {
                $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                if (!$jiedan_lock->get()) {
                    // 获取锁定5秒...
                    $logs = [
                        "des" => "【闪送接单】派单后接单了",
                        "status" => $order->status,
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("【派单后接单了】", $logs);
                }
                $before_time = time();
                Log::info($log_prefix . "取货中-睡眠之前：" . date("Y-m-d H:i:s", $before_time));
                sleep(1);
                $after_time = time();
                Log::info($log_prefix . "取货中-睡眠之后：" . date("Y-m-d H:i:s", $after_time));
                // 取货中
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调，订单状态不正确，不能操作接单');
                    // $logs = [
                    //     "des" => "【闪送订单回调】接单回调，订单状态不正确，不能操作接单",
                    //     "date" => date("Y-m-d H:i:s"),
                    //     "mq_ps" => $order->ps,
                    //     "mq_status" => $order->status,
                    //     "ss_status" => $status,
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
                    //     "des" => "【闪送订单回调】设置锁失败",
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dd->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return ['status' => 0, 'msg' => 'err', 'data' => ''];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30) || ($order->dd_status > 30) || ($order->mqd_status > 30)) {
                    // $logs = [
                    //     "des" => "【闪送订单回调】闪送接单，美团蜂鸟已经接过单了",
                    //     "mt_status" => $order->mt_status,
                    //     "ss_status" => $order->ss_status,
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
                        //     "des" => "【闪送订单回调】美团待接单取消失败",
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
                        //     "des" => "【闪送订单回调】蜂鸟待接单取消失败",
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
                // 取消美全达订单
                if ($order->mqd_status === 20 || $order->mqd_status === 30) {
                    $meiquanda = app("meiquanda");
                    $result = $meiquanda->repealOrder($order->mqd_order_id);
                    if ($result['code'] != 100) {
                        // $logs = [
                        //     "des" => "【闪送订单回调】美全达待接单取消失败",
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
                        //     "des" => "【闪送订单回调】达达待接单取消失败",
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
                        //     "des" => "【闪送订单回调】UU待接单取消失败",
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
                    $this->cancelRiderOrderMeiTuanZhongBao($order, 6);
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone, $locations) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 3,
                            'money' => $order->money_ss,
                            'profit' => 0,
                            'status' => 50,
                            'ss_status' => 50,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'fn_status' => $order->ss_status < 20 ?: 7,
                            'mqd_status' => $order->mqd_status < 20 ?: 7,
                            'dd_status' => $order->dd_status < 20 ?: 7,
                            'uu_status' => $order->uu_status < 20 ?: 7,
                            'sf_status' => $order->sf_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->ss_order_id,
                            'courier_name' => $name,
                            'courier_phone' => $phone,
                            'courier_lng' => $locations['lng'] ?? '',
                            'courier_lat' => $locations['lat'] ?? '',
                            'pay_status' => 1,
                            'pay_at' => date("Y-m-d H:i:s"),
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
                            "type" => 2,
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
                $order->courier_lng = $locations['lng'] ?? '';
                $order->courier_lat = $locations['lat'] ?? '';
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
                $shop = Shop::select('id', 'running_add')->find($order->shop_id);
                // 已送达【已完成】
                $order->profit = $shop->running_add;
                $order->add_money = $shop->running_add;
                $order->status = 70;
                $order->ss_status = 70;
                $order->over_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $order->receiver_lng;
                $order->courier_lat = $order->receiver_lat;
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
                if ($abort_type < 3) {
                    Log::info($log_prefix . "闪送取消订单通知-商户原因取消");
                    // $logs = [
                    //     "\n\n描述" => "闪送取消订单通知-商户原因取消",
                    //     "\n\n订单ID" => $order->id,
                    //     "\n\n订单号" => $order->order_id,
                    //     "\n\n订单配送单号" => $order->peisong_id,
                    //     "\n\n订单闪送单号" => $order->ss_order_id,
                    //     "\n\n请求闪送单号" => $ss_order_id,
                    //     "\n\n时间" => date("Y-m-d H:i:s"),
                    // ];
                    // $dd->sendMarkdownMsgArray("【闪送跑腿】，取消订单-商户原因", $logs);
                    // return json_encode(['status' => 200, 'msg' => '', 'data' => '']);
                }
                if ($order->status >= 20 && $order->status < 70 ) {
                    // 添加延时
                    $before_time = time();
                    Log::info($log_prefix . "接口取消订单-睡眠之前：" . date("Y-m-d H:i:s", $before_time));
                    sleep(1);
                    $after_time = time();
                    Log::info($log_prefix . "接口取消订单-睡眠之后：" . date("Y-m-d H:i:s", $after_time));
                    // 判断闪送订单号
                    if ($order->ss_order_id !== $ss_order_id) {
                        Log::info($log_prefix . "接口取消订单闪送单号不符合|订单中配送单号：{$order->peisong_id}|订单中闪送单号：{$order->ss_order_id}|请求闪送单号：{$ss_order_id}");
                        $logs = [
                            "\n\n描述" => "接口取消订单闪送单号不符合",
                            "\n\n订单ID" => $order->id,
                            "\n\n订单号" => $order->order_id,
                            "\n\n订单配送单号" => $order->peisong_id,
                            "\n\n订单闪送单号" => $order->ss_order_id,
                            "\n\n请求闪送单号" => $ss_order_id,
                            "\n\n时间" => date("Y-m-d H:i:s"),
                        ];
                        $dd->sendMarkdownMsgArray("【闪送跑腿】，取消单号错误", $logs);
                        return json_encode(['status' => 200, 'msg' => '', 'data' => '']);
                    }
                    $logs = [
                        "\n\n描述" => "【闪送跑腿】，发起取消配送",
                        "\n\n订单ID" => $order->id,
                        "\n\n订单号" => $order->order_id,
                        "\n\n时间" => date("Y-m-d H:i:s"),
                        "\n\n睡眠之前时间戳" => $before_time,
                        "\n\n睡眠之后时间戳" => $after_time,
                    ];
                    $dd->sendMarkdownMsgArray("【闪送跑腿】，发起取消配送", $logs);
                    Log::info($log_prefix . '接口取消订单成功');
                    // 操作退款
                    try {
                        DB::transaction(function () use ($order, $name, $phone, $log_prefix) {
                            if (($order->status == 50 || $order->status == 60) && $order->ps == 3) {
                                // 查询当前用户，做余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                // DB::table("user_money_balances")->insert();
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "取消闪送跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_ss);
                                Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            }
                            $update_data = [
                                'ss_status' => 99
                            ];
                            Order::where("id", $order->id)->update($update_data);
                            OrderLog::create([
                                'ps' => 3,
                                'order_id' => $order->id,
                                'des' => '【闪送】跑腿，发起取消配送',
                            ]);
                            if (in_array($order->zb_status, [0,1,3,7,80,99]) && in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->uu_status, [0,1,3,7,80,99])) {
                                $update_data = [
                                    'status' => 0,
                                    'ss_status' => 0,
                                    'ps' => 0
                                ];
                                Order::where("id", $order->id)->update($update_data);
                                dispatch(new CreateMtOrder($order, 2));
                                OrderLog::create([
                                    'ps' => 3,
                                    'order_id' => $order->id,
                                    'des' => '【闪送】跑腿，发起取消配送，系统重新派单',
                                ]);
                            }

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
                } else {
                    Log::info($log_prefix . "取消订单，状态不正确。状态(status)：{$order->status}");
                }
                return json_encode($res);
            }
        }
        return json_encode($res);
    }

    /**
     * 待接单
     */
    public function pendingOrder($ss_order_id)
    {
        $this->log_tool2_prefix = str_replace('###', "派单回调,闪送单号:{$ss_order_id}", $this->log_prefix);
        $res = ['status' => 200, 'msg' => '', 'data' => ''];
        if ($delivery = OrderDelivery::where('three_order_no', $ss_order_id)->first()) {
            $this->log_info("跑腿订单ID{$delivery->order_id}");
            OrderDeliveryTrack::create([
                'order_id' => $delivery->order_id,
                'wm_id' => $delivery->wm_id,
                'delivery_id' => $delivery->id,
                'status' => 20,
                'status_des' => '',
                'description' => '等待骑手接单',
            ]);
        }
        return json_encode($res);
    }

    /**
     * 取货中
     */
    public function pickingUp($ss_order_id, $name, $phone, $longitude, $latitude, $locations)
    {
        $this->log_tool2_prefix = str_replace('###', "[接单回调,闪送单号:{$ss_order_id}]", $this->log_prefix);
        $res = ['status' => 200, 'msg' => '', 'data' => ''];
        if ($delivery = OrderDelivery::where('three_order_no', $ss_order_id)->first()) {
            $this->log_info("跑腿订单ID{$delivery->order_id}");
            // 添加配送记录
            OrderDeliveryTrack::create([
                'order_id' => $delivery->order_id,
                'wm_id' => $delivery->wm_id,
                'delivery_id' => $delivery->id,
                'status' => 50,
                'status_des' => '',
                'description' => '骑手已手接单',
            ]);
            if ($delivery->status > 50) {
                $this->ding_error("闪送接单回调，订单状态异常|运单状态:{$delivery->status}|运单ID:{$delivery->id}|跑腿订单ID:{$delivery->order_id}|闪送订单{$ss_order_id}");
            }
            // 查找订单
            if ($order = Order::find($delivery->order_id)) {
                $this->log_tool2_prefix = str_replace('###', "[接单回调,闪送单号:{$ss_order_id}|跑腿订单ID:{$delivery->order_id}|跑腿订单号:{$order->order_id}|跑腿订单状态:{$order->status}]", $this->log_prefix);
                $this->notice_tool2_prefix = str_replace('###', "[接单回调,闪送单号:{$ss_order_id}|跑腿订单ID:{$delivery->order_id}|跑腿订单号:{$order->order_id}|跑腿订单状态:{$order->status}]", $this->log_prefix);
                $this->log_info("存在跑腿订单");
                if ($order->status == 99) {
                    $this->log_info("订单已是取消状态");
                    $this->ding_error("订单已是取消状态");
                    return json_encode($res);
                }
                if ($order->status == 70) {
                    $this->log_info("订单已是完成");
                    $this->ding_error("订单已是完成");
                    return json_encode($res);
                }
                // 如果订单已经有配送平台了，配送平台不是【闪送】发起取消
                if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 3) ) {
                    $this->log_info("订单已经有配送平台了，配送平台不是闪送,发起取消-开始");
                    $this->ding_error("订单已经有配送平台了，配送平台不是闪送,发起取消-开始");
                    $cancel_result = $this->cancelShansongOrder($ss_order_id, '单已经有配送平台了，配送平台不是闪送');
                    if ($cancel_result['status'] !== true) {
                        $this->log_info("订单已经有配送平台了，配送平台不是闪送,发起取消-取消失败");
                        $this->ding_error("订单已经有配送平台了，配送平台不是闪送,发起取消-取消失败");
                    }
                    $this->log_info("订单已经有配送平台了，配送平台不是闪送,发起取消-取消成功");
                    $this->ding_error("订单已经有配送平台了，配送平台不是闪送,发起取消-取消成功");
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 3,
                        "order_id" => $order->id,
                        "des" => "已有平台配送，取消[闪送]跑腿订单",
                        'name' => $name,
                        'phone' => $phone,
                    ]);
                    return json_encode($res);
                }
                // 开始接单逻辑
                // 开始接单逻辑
                // 开始接单逻辑
                $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 5);
                if (!$jiedan_lock->get()) {
                    // 获取锁，5秒...
                    $this->ding_error("闪送重复接单");
                    $this->log_info("闪送重复接单");
                }
                $before_time = time();
                $this->log_info( "取货中-睡眠之前：" . date("Y-m-d H:i:s", $before_time));
                // 睡眠1秒，防止发单闪送接单时，发单状态还未改变
                sleep(1);
                $after_time = time();
                $this->log_info( "取货中-睡眠之后：" . date("Y-m-d H:i:s", $after_time));
                // 取货中
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    $this->log_info( '订单状态不正确，不能操作接单');
                    $this->ding_error("订单状态不正确，不能操作接单");
                    return json_encode($res);
                }
                // 取消各个平台待接单订单
                // 取消各个平台待接单订单
                // 取消各个平台待接单订单
                $cancel_text = '闪送已接单';
                // 取消美团订单
                if ($order->mt_status === 20 || $order->mt_status === 30) {
                    $this->cancelMeituanOrder($delivery->three_order_no, $order->delivery_id, $order->id, $order->order_id, $cancel_text);
                }
                // 取消达达订单
                if ($order->dd_status === 20 || $order->dd_status === 30) {
                    if ($order->shipper_type_dd) {
                        $this->cancelDadaOwnOrder($order->warehouse_id ?: $order->shop_id, $order->id, $order->order_id, $cancel_text);
                    } else {
                        $this->cancelDadaOrder($order->id, $order->order_id, $cancel_text);
                        $dada = app("dada");
                    }
                }
                // 取消UU订单
                if ($order->uu_status === 20 || $order->uu_status === 30) {
                    $this->cancelUuOrder($order->id, $order->order_id, $cancel_text);
                }
                // 取消顺丰订单
                if ($order->sf_status === 20 || $order->sf_status === 30) {
                    if ($order->shipper_type_sf) {
                        $this->cancelShunfengOwnOrder($order->warehouse_id ?: $order->shop_id, $order->delivery_id, $order->id, $order->order_id, $cancel_text);
                    } else {
                        $this->cancelShunfengOrder($order->warehouse_id ?: $order->shop_id, $order->delivery_id, $order->id, $order->order_id, $cancel_text);
                    }
                }
                // 取消众包跑腿
                if ($order->zb_status === 20 || $order->zb_status === 30) {
                    $this->cancelRiderOrderMeiTuanZhongBao($order, 6);
                }
            }
        }
        return json_encode($res);
    }

    /**
     * 送货中
     * @data 2023/7/31 2:54 下午
     */
    public function Delivering($ss_order_id, $name, $phone, $longitude, $latitude, $locations)
    {
        $this->prefix = str_replace('###', "接单回调单号:{$ss_order_id}", $this->log_prefix);
        $res = ['status' => 200, 'msg' => '', 'data' => ''];
        return json_encode($res);
    }
}
