<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCancel;
use App\Events\OrderComplete;
use App\Jobs\CreateMtOrder;
use App\Jobs\MtLogisticsSync;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\OrderLog;
use App\Models\OrderResend;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use App\Traits\RiderOrderCancel;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ShunfengController
{
    use RiderOrderCancel;

    public function status(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        Log::info('顺丰跑腿回调-订单状态变更-全部参数', $request->all());
        // 商家订单ID
        $order_id = $request->get("shop_order_id", "");
        // 配送员
        $name = $request->get("operator_name", "");
        $phone = $request->get("operator_phone", "");
        // 配送员位置经度纬度
        $rider_lng = $request->get("rider_lng", "");
        $rider_lat = $request->get("rider_lat", "");
        $locations = ['lng' => $rider_lng, 'lat' => $rider_lat];
        // 10-配送员确认;12:配送员到店;15:配送员配送中
        $status = $request->get("order_status", "");
        $status_desc = $request->get("status_desc", "");

        if (in_array($status, [10, 15])) {
            Log::info("顺丰配送员坐标|order_id:{$order_id}，status:{$status}", ['lng' => $rider_lng, 'lat' => $rider_lat]);
        }

        if ($order = Order::where('delivery_id', $order_id)->first()) {
            // 如果是接单状态，设置接单锁
            if ($status == 10) {
                $_time1 = time();
                try {
                    // 获取接单状态锁，如果锁存在，等待8秒
                    Cache::lock("jiedan_lock:{$order->id}", 3)->block(8);
                    // 获取锁成功
                } catch (LockTimeoutException $e) {
                    // 获取锁失败
                    $this->ding_error("集合顺丰|接单获取锁失败错误|{$order->id}|{$order->order_id}：" . json_encode($request->all(), JSON_UNESCAPED_UNICODE));
                }
                $_time2 = time();
                // 重新查找订单，防止锁之前更换状态
                if ($_time1 !== $_time2) {
                    $order = Order::find($order->id);
                }
            }
            // 跑腿运力
            $delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
            // 日志前缀
            $log_prefix = "[顺丰跑腿回调-订单状态变更|订单号:{$order_id}|订单状态:{$order->status}|请求状态:{$status}]-";

            // 判断状态
            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }

            // 钉钉报警提醒
            $dingding = app("ding");

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是【顺丰】发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 7)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【顺丰】发起取消-开始');
                $logs = [
                    "des" => "【顺丰订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【顺丰】发起取消-开始",
                    "id" => $order->id,
                    "order_id" => $order->order_id
                ];
                $dingding->sendMarkdownMsgArray("【ERROR】已有配送平台", $logs);
                $sf = app("shunfeng");
                $result = $sf->cancelOrder($order);
                if ( ($result['error_code'] != 0) && (strstr($result['error_msg'], '已取消') === false) ) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【顺丰】发起取消-失败');
                    $logs = [
                        "des" => "【顺丰订单回调】订单状态不是0，并且订单已经有配送平台了，配送平台不是【顺丰】发起取消-失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【ERROR】顺丰取消订单失败", $logs);
                    return json_encode($res);
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 7,
                    "order_id" => $order->id,
                    "des" => "取消【顺丰】跑腿订单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是【顺丰】发起取消-成功');
                // 取消足迹记录
                if ($delivery) {
                    $delivery->update([
                        'delivery_name' => $name,
                        'delivery_phone' => $phone,
                        'status' => 99,
                        'cancel_at' => date("Y-m-d H:i:s"),
                        'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    ]);
                    try {
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $delivery->id,
                                'status' => 99,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                            ], [
                                'order_id' => $delivery->order_id,
                                'wm_id' => $delivery->wm_id,
                                'delivery_id' => $delivery->id,
                                'status' => 99,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("聚合顺丰-接单回调取消舒服-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("聚合顺丰-接单回调取消舒服-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                return json_encode($res);
            }

            // 回调状态判断
            // 10-配送员确认;12:配送员到店;15:配送员配送中
            if ($status == 10) {
                // 写入接单足迹
                if ($delivery) {
                    try {
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $delivery->id,
                                'status' => 50,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_RECEIVING,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                            ], [
                                'order_id' => $delivery->order_id,
                                'wm_id' => $delivery->wm_id,
                                'delivery_id' => $delivery->id,
                                'status' => 50,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_RECEIVING,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                                'description' => "配送员: {$name} <br>联系方式：{$phone}",
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("聚合顺丰-接单回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("聚合顺丰-接单回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                if (strpos($status_desc, '改派') !== false) {
                    // 配送员配送中
                    $order->courier_name = $name;
                    $order->courier_phone = $phone;
                    $order->save();
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 7,
                        "order_id" => $order->id,
                        "des" => "[顺丰]跑腿，配送员已改派",
                        'name' => $name,
                        'phone' => $phone,
                    ]);
                    Log::info($log_prefix . '顺丰配送员已改派，更改信息成功');
                    return json_encode($res);
                }
                // $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                // if (!$jiedan_lock->get()) {
                //     // 获取锁定5秒...
                //     $logs = [
                //         "des" => "【顺丰接单】派单后接单了",
                //         "status" => $order->status,
                //         "id" => $order->id,
                //         "order_id" => $order->order_id
                //     ];
                //     $dingding->sendMarkdownMsgArray("【派单后接单了】", $logs);
                //     sleep(1);
                // }
                // 配送员确认
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调，订单状态不正确，不能操作接单');
                    // $logs = [
                    //     "des" => "【顺丰订单回调】接单回调，订单状态不正确，不能操作接单",
                    //     "date" => date("Y-m-d H:i:s"),
                    //     "mq_ps" => $order->ps,
                    //     "mq_status" => $order->status,
                    //     "sf_status" => $status,
                    //     "id" => $order->id,
                    //     "order_id" => $order->order_id
                    // ];
                    // $dingding->sendMarkdownMsgArray("【ERROR】【顺丰】不能操作接单", $logs);
                    return json_encode($res);
                }

                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    Log::info($log_prefix . '设置锁失败');
                    $logs = [
                        "des" => "【顺丰订单回调】设置锁失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("【ERROR】设置锁失败", $logs);
                    return [];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);

                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30) || ($order->ss_status > 30) || ($order->mqd_status > 30)) {
                    $logs = [
                        "des" => "【顺丰订单回调】顺丰接单，其它平台已经接过单了",
                        "mt_status" => $order->mt_status,
                        "sf_status" => $order->sf_status,
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
                            "des" => "【顺丰订单回调】美团待接单取消失败",
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
                            "des" => "【顺丰订单回调】蜂鸟待接单取消失败",
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
                            "des" => "【顺丰订单回调】闪送待接单取消失败",
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
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 3, '聚合顺丰');
                    Log::info($log_prefix . '取消闪送待接单订单成功');
                }
                // 取消美全达订单
                if ($order->mqd_status === 20 || $order->mqd_status === 30) {
                    $meiquanda = app("meiquanda");
                    $result = $meiquanda->repealOrder($order->mqd_order_id);
                    if ($result['code'] != 100) {
                        $logs = [
                            "des" => "【顺丰订单回调】美全达待接单取消失败",
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
                            "des" => "【顺丰订单回调】达达待接单取消失败",
                            "id" => $order->id,
                            "order_id" => $order->order_id
                        ];
                        $dingding->sendMarkdownMsgArray("【ERROR】达达待接单取消失败", $logs);
                    }
                    OrderLog::create([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '取消[达达]跑腿订单',
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 5, '聚合顺丰');
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
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 6, '聚合顺丰');
                    Log::info($log_prefix . '取消UU待接单订单成功');
                }
                // 取消众包跑腿
                if ($order->zb_status === 20 || $order->zb_status === 30) {
                    $this->cancelRiderOrderMeiTuanZhongBao($order, 10);
                }

                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone, $rider_lng, $rider_lat, $delivery) {
                        if ($delivery) {
                            OrderDelivery::where('id', $delivery->id)->update([
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                                'is_payment' => 1,
                                'status' => 50,
                                'paid_at' => date("Y-m-d H:i:s"),
                                'arrival_at' => date("Y-m-d H:i:s"),
                                'track' => OrderDeliveryTrack::TRACK_STATUS_RECEIVING,
                            ]);
                        }
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 7,
                            'money' => $order->money_sf,
                            'profit' => 0,
                            'status' => 50,
                            'sf_status' => 50,
                            'uu_status' => $order->uu_status < 20 ?: 7,
                            'dd_status' => $order->dd_status < 20 ?: 7,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'fn_status' => $order->fn_status < 20 ?: 7,
                            'ss_status' => $order->ss_status < 20 ?: 7,
                            'mqd_status' => $order->mqd_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->sf_order_id,
                            'courier_name' => $name,
                            'courier_phone' => $phone,
                            'courier_lng' => $rider_lng,
                            'courier_lat' => $rider_lat,
                            'pay_status' => 1,
                            'pay_at' => date("Y-m-d H:i:s"),
                        ]);
                        // 查找扣款用户，为了记录余额日志
                        $current_user = DB::table('users')->find($order->user_id);
                        // 减去用户配送费
                        DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_sf);
                        // 用户余额日志
                        // DB::table("user_money_balances")->insert();
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money_sf,
                            "type" => 2,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money - $order->money_sf),
                            "description" => "顺丰跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 7,
                            "order_id" => $order->id,
                            "des" => "【顺丰】跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    Log::info($log_prefix . "顺丰接单，更改信息成功，扣款成功。扣款：{$order->money_sf}");
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    Log::info($log_prefix . '更改信息、扣款事务提交失败', $message);
                    $logs = [
                        "des" => "【顺丰订单回调】更改信息、扣款失败",
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
            }elseif ($status == 12) {
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'status' => 50,
                            'atshop_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_DELIVERING,
                        ]);
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $delivery->id,
                                'status' => 50,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_PICKING,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                            ], [
                                'order_id' => $delivery->order_id,
                                'wm_id' => $delivery->wm_id,
                                'delivery_id' => $delivery->id,
                                'status' => 50,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_PICKING,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                                'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_PICKING,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("自有顺丰-到店回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有顺丰-到店回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                return json_encode($res);
            }elseif ($status == 15) {
                // 10-配送员确认;12:配送员到店;15:配送员配送中
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'status' => 60,
                            'pickup_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_DELIVERING,
                        ]);
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $delivery->id,
                                'status' => 60,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_DELIVERING,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                            ], [
                                'order_id' => $delivery->order_id,
                                'wm_id' => $delivery->wm_id,
                                'delivery_id' => $delivery->id,
                                'status' => 60,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_DELIVERING,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                                'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_DELIVERING,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("自有顺丰-取货回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有顺丰-取货回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // 配送员配送中
                $order->status = 60;
                $order->sf_status = 60;
                $order->take_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $rider_lng;
                $order->courier_lat = $rider_lat;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 7,
                    "order_id" => $order->id,
                    "des" => "【顺丰】跑腿，配送中",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            }
        }

        return json_encode($res);
    }

    public function complete(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        Log::info('顺丰跑腿回调-订单完成回调-全部参数', $request->all());
        // 商家订单ID
        $order_id = $request->get("shop_order_id", "");
        // 配送员
        $name = $request->get("operator_name", "");
        $phone = $request->get("operator_phone", "");
        // 配送员位置经度
        $rider_lng = $request->get("rider_lng", "");
        $rider_lat = $request->get("rider_lat", "");
        $locations = ['lng' => $rider_lng, 'lat' => $rider_lat];
        // 10-配送员确认;12:配送员到店;15:配送员配送中
        $status = $request->get("order_status", "");
        Log::info("顺丰配送员坐标|order_id:{$order_id}，status:{$status}", ['lng' => $rider_lng, 'lat' => $rider_lat]);
        // 签收类型	1:正常签收, 2:商家退回签收
        $receipt_type = $request->get("receipt_type", 1);

        if ($order = Order::where('delivery_id', $order_id)->first()) {
            if ($receipt_type === 2) {
                Log::info("商家退回签收|order_id:{$order_id}，status:{$status}", ['lng' => $rider_lng, 'lat' => $rider_lat]);
            }
            // 跑腿运力
            $delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
            // 写入完成足迹
            if ($delivery) {
                try {
                    $delivery->update([
                        'delivery_name' => $name,
                        'delivery_phone' => $phone,
                        'delivery_lng' => $locations['lng'] ?? '',
                        'delivery_lat' => $locations['lat'] ?? '',
                        'status' => 70,
                        'finished_at' => date("Y-m-d H:i:s"),
                        'track' => $receipt_type === 2 ? OrderDeliveryTrack::TRACK_STATUS_RETURN : OrderDeliveryTrack::TRACK_STATUS_FINISH,
                    ]);
                    OrderDeliveryTrack::firstOrCreate(
                        [
                            'delivery_id' => $delivery->id,
                            'status' => 70,
                            'status_des' => $receipt_type === 2 ? OrderDeliveryTrack::TRACK_STATUS_RETURN : OrderDeliveryTrack::TRACK_STATUS_FINISH,
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                        ], [
                            'order_id' => $delivery->order_id,
                            'wm_id' => $delivery->wm_id,
                            'delivery_id' => $delivery->id,
                            'status' => 70,
                            'status_des' => $receipt_type === 2 ? OrderDeliveryTrack::TRACK_STATUS_RETURN : OrderDeliveryTrack::TRACK_STATUS_FINISH,
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'description' => $receipt_type === 2 ? OrderDeliveryTrack::TRACK_DESCRIPTION_FINISH2 : OrderDeliveryTrack::TRACK_DESCRIPTION_FINISH,
                        ]
                    );
                } catch (\Exception $exception) {
                    Log::info("聚合顺丰-送达回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    $this->ding_error("聚合顺丰-送达回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                }
            }

            // 日志前缀
            $log_prefix = "[顺丰跑腿回调-订单完成回调|订单号:{$order_id}|订单状态:{$order->status}|请求状态:{$status}]-";

            // 判断状态
            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }

            $shop = Shop::select('id', 'running_add')->find($order->shop_id);
            // 已送达【已完成】
            $order->profit = $shop->running_add;
            $order->add_money = $shop->running_add;
            $order->status = 70;
            $order->sf_status = 70;
            $order->over_at = date("Y-m-d H:i:s");
            $order->courier_name = $name;
            $order->courier_phone = $phone;
            $order->courier_lng = $order->receiver_lng;
            $order->courier_lat = $order->receiver_lat;
            $order->save();
            // 记录订单日志
            OrderLog::create([
                'ps' => 7,
                "order_id" => $order->id,
                "des" => $receipt_type === 2 ? "【顺丰】跑腿，商家退回签收" : "【顺丰】跑腿，已送达",
                'name' => $name,
                'phone' => $phone,
            ]);
            if ($receipt_type === 1) {
                dispatch(new MtLogisticsSync($order));
                event(new OrderComplete($order->id, $order->user_id, $order->shop_id, date("Y-m-d", strtotime($order->created_at))));
            }
        }

        return json_encode($res);
    }

    public function cancel(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        Log::info('顺丰跑腿回调-订单取消回调-全部参数', $request->all());
        // 商家订单ID
        $order_id = $request->get("shop_order_id", "");
        // 配送员
        $name = $request->get("operator_name", "");
        $phone = $request->get("operator_phone", "");
        // 配送员位置经度
        $rider_lng = $request->get("rider_lng", "");
        // 配送员位置纬度
        $rider_lat = $request->get("rider_lat", "");
        // 10-配送员确认;12:配送员到店;15:配送员配送中
        // $status = $request->get("order_status", "");

        // $receipt_type = $request->get("receipt_type", 1);

        sleep(1);
        if ($order = Order::where('delivery_id', $order_id)->first()) {
            // 跑腿运力
            $delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
            // 写入足迹
            if ($delivery) {
                try {
                    $delivery->update([
                        'delivery_name' => $name,
                        'delivery_phone' => $phone,
                        'status' => 99,
                        'cancel_at' => date("Y-m-d H:i:s"),
                        'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    ]);
                    OrderDeliveryTrack::firstOrCreate(
                        [
                            'delivery_id' => $delivery->id,
                            'status' => 99,
                            'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                        ], [
                            'order_id' => $delivery->order_id,
                            'wm_id' => $delivery->wm_id,
                            'delivery_id' => $delivery->id,
                            'status' => 99,
                            'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $rider_lng,
                            'delivery_lat' => $rider_lat,
                        ]
                    );
                } catch (\Exception $exception) {
                    Log::info("聚合顺丰-取消回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    $this->ding_error("聚合顺丰-取消回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                }
            }
            // 日志前缀
            $log_prefix = "[顺丰跑腿回调-订单取消回调|订单号:{$order_id}|订单状态:{$order->status}]-";

            // 判断状态
            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
                return json_encode($res);
            }
            if ($order->status == 70) {
                Log::info($log_prefix . '订单已是完成');
                return json_encode($res);
            }
            // 钉钉报警提醒
            $dingding = app("ding");

            if ($order->status >= 20 && $order->status < 70 ) {
                try {
                    DB::transaction(function () use ($order, $log_prefix, $dingding, $name, $phone, $delivery) {
                        OrderDelivery::where('id', $delivery->id)->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'status' => 99,
                            'cancel_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        ]);
                        $update_data = [
                            'sf_status' => 99
                        ];
                        if (in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) &&
                            in_array($order->zb_status, [0,1,3,7,80,99]) &&
                            in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) &&
                            in_array($order->uu_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99])) {
                            $update_data = [
                                'status' => 99,
                                'sf_status' => 99
                            ];
                        }
                        Order::where("id", $order->id)->update($update_data);
                        OrderLog::create([
                            'ps' => 7,
                            'order_id' => $order->id,
                            'des' => '【顺丰】跑腿，发起取消配送',
                        ]);
                        if (in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) &&
                            in_array($order->zb_status, [0,1,3,7,80,99]) &&
                            in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) &&
                            in_array($order->uu_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99])) {
                            $update_data = [
                                'status' => 0,
                                'sf_status' => 0,
                                'ps' => 0
                            ];
                            Order::where("id", $order->id)->update($update_data);
                            Log::info($log_prefix . '顺丰发起取消配送，系统重新呼叫跑腿');
                            dispatch(new CreateMtOrder($order, 2));
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
                        "des" => "【顺丰订单回调】更改信息、将钱返回给用户失败",
                        "id" => $order->id,
                        "order_id" => $order->order_id
                    ];
                    $dingding->sendMarkdownMsgArray("顺丰接口取消订单将钱返回给用户失败", $logs);
                    return json_encode(['code' => 100]);
                }

                // 操作退款
                if ($delivery->is_payment == 1 && $delivery->is_refund == 0) {
                    try {
                        DB::transaction(function () use ($order, $delivery) {
                            if (($order->status == 50 || $order->status == 60) && $order->ps == 7) {
                                // 查询当前用户，做余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                // DB::table("user_money_balances")->insert();
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "取消顺丰跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_sf);
                                $this->ding_error("接口取消顺丰订单，将钱返回给用户|{$order->order_id}|");
                            }
                        });
                    } catch (\Exception $e) {
                        $this->ding_error("聚合顺丰，取消回调，退款失败", [$e->getCode(),$e->getMessage(),$e->getLine(),$e->getFile()]);
                    }
                }
                Log::info($log_prefix . '接口取消订单成功');
            } else {
                Log::info($log_prefix . "取消订单，状态不正确。状态(status)：{$order->status}");
            }
        }

        return json_encode($res);
    }

    public function auth(Request $request)
    {
        Log::info('顺丰-授权回调', $request->all());

        $res = [
            "error_code" => 0,
            "error_msg" => "success"
        ];

        return json_encode($res);
    }

    public function cancelQishou(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        Log::info('顺丰跑腿回调-骑手撤单-全部参数', $request->all());
        // 商家订单ID
        $order_id = $request->get("shop_order_id", "");
        // $this->ding_error("顺丰骑手撤单：{$order_id}");
        if ($order = Order::where('delivery_id', $order_id)->first()) {
            if ((int) $order->ps === 7) {
                $sf = app("shunfeng");
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0) {
                    // 跑腿运力
                    $delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                    // 写入足迹
                    if ($delivery) {
                        try {
                            $delivery->update([
                                'status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s"),
                                'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                            ]);
                            OrderDeliveryTrack::firstOrCreate(
                                [
                                    'delivery_id' => $delivery->id,
                                    'status' => 99,
                                    'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                ], [
                                    'order_id' => $delivery->order_id,
                                    'wm_id' => $delivery->wm_id,
                                    'delivery_id' => $delivery->id,
                                    'status' => 99,
                                    'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                ]
                            );
                        } catch (\Exception $exception) {
                            Log::info("聚合顺丰-取消回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                            $this->ding_error("聚合顺丰-取消回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                        }
                    }
                    if (($order->status == 50 || $order->status == 60) && $order->ps == 7) {
                        // $this->ding_error("顺丰骑手撤单：{$order_id}，返还配送费");
                        // 查询当前用户，做余额日志
                        $current_user = DB::table('users')->find($order->user_id);
                        // DB::table("user_money_balances")->insert();
                        UserMoneyBalance::create([
                            "user_id" => $order->user_id,
                            "money" => $order->money,
                            "type" => 1,
                            "before_money" => $current_user->money,
                            "after_money" => ($current_user->money + $order->money),
                            "description" => "顺丰骑手撤单取消顺丰跑腿订单：" . $order->order_id,
                            "tid" => $order->id
                        ]);
                        // 将配送费返回
                        DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_sf);
                    }
                    OrderLog::create([
                        'ps' => 7,
                        'order_id' => $order->id,
                        'des' => '「顺丰」跑腿骑手撤单，取消顺丰跑腿订单',
                    ]);
                    $delivery_id = $order->order_id . (OrderResend::where('order_id', $order->id)->count() + 1);
                    OrderResend::create(['order_id' => $order->id, 'delivery_id' => $delivery_id, 'user_id' => 0]);
                    $order->delivery_id = $delivery_id;
                    $order->mt_status = 0;
                    $order->fail_mt = '';

                    $order->fn_status = 0;
                    $order->fail_fn = '';

                    $order->ss_status = 0;
                    $order->fail_ss = '';

                    $order->dd_status = 0;
                    $order->fail_dd = '';

                    $order->uu_status = 0;
                    $order->fail_uu = '';

                    $order->sf_status = 0;
                    // $order->fail_sf = '骑手撤单，重新发送不选择';
                    $order->fail_sf = '';

                    $order->zb_status = 0;
                    $order->fail_zb = '';

                    $order->status = 8;
                    $order->ps = 0;
                    $order->shipper_type_ss = 0;
                    $order->shipper_type_dd = 0;
                    $order->shipper_type_sf = 0;
                    $order->save();
                    $order = Order::find($order->id);
                    dispatch(new CreateMtOrder($order));
                    OrderLog::create([
                        'ps' => 7,
                        'order_id' => $order->id,
                        'des' => '「顺丰」跑腿骑手撤单，重新派单',
                    ]);
                    event(new OrderCancel($order->id, 7));
                } else {
                    Log::info('顺丰跑腿回调-骑手撤单-取消顺丰跑腿订单失败');
                }
            } else {
                Log::info('顺丰跑腿回调-骑手撤单-不是顺丰配送');
            }
        } else {
            Log::info('顺丰跑腿回调-骑手撤单-未找到订单');
        }
        return json_encode($res);
    }

    public function exceptionQishou(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        Log::info('顺丰跑腿回调-异常订单-全部参数', $request->all());
        // 钉钉报警提醒
        $dingding = app("ding");
        $logs = [
            "des" => "【顺丰订单回调】异常订单",
            "request" => json_encode($request->all())
        ];
        $dingding->sendMarkdownMsgArray("顺丰跑腿回调-异常订单", $logs);
        return json_encode($res);
    }
}
