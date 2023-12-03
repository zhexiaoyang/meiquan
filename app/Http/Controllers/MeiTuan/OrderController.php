<?php

namespace App\Http\Controllers\MeiTuan;

use App\Jobs\CreateMtOrder;
use App\Jobs\MtLogisticsSync;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use App\Traits\RiderOrderCancel;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
        // 0 订单待调度，20 订单已接单，30 订单已取货，50 订单已送达，99 订单已取消
        $status = $request->get('status', '');
        $delivery_id = $request->get('delivery_id', 0);
        $mt_peisong_id = $request->get('mt_peisong_id', '');
        $data = $request->only(['courier_name', 'courier_phone', 'cancel_reason_id', 'cancel_reason','status']);
        // 配送员姓名
        $name = $data['courier_name'] ?? '';
        // 配送员手机号
        $phone = $data['courier_phone'] ?? '';
        // 定义日志格式
        $log_prefix = "[美团跑腿回调-订单|订单号:{$delivery_id}]-";
        Log::info($log_prefix . '全部参数', $data);
        $dd = app("ding");
        // 查询订单
        if (($order = Order::where('delivery_id', $delivery_id)->first()) && in_array($status, [0, 20, 30, 50, 99])) {
            $log_prefix = "[美团跑腿回调-订单|订单号:{$delivery_id}|订单状态:{$order->status}|请求状态:{$status}]-";
            // 如果是接单状态，设置接单锁
            if ($status == 20) {
                try {
                    // 获取接单状态锁，如果锁存在，等待8秒
                    Cache::lock("jiedan_lock:{$order->id}", 3)->block(8);
                    // 获取锁成功
                } catch (LockTimeoutException $e) {
                    // 获取锁失败
                    $this->ding_error("美团跑腿|接单获取锁失败错误|{$order->id}|{$order->order_id}：" . json_encode($request->all(), JSON_UNESCAPED_UNICODE));
                }
            }
            // 跑腿运力
            $delivery = OrderDelivery::where('three_order_no', $mt_peisong_id)->first();
            if ($order->status == 99) {
                Log::info($log_prefix . '订单已是取消状态');
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
                        Log::info("美团跑腿-取消回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("美团跑腿-取消回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
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

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是[美团]发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 1)) {
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是[美团]发起取消-开始');
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $mt_peisong_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] !== 0) {
                    Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是[美团]发起取消-失败');
                    return json_encode(['code' => 100]);
                }
                OrderLog::create([
                    'ps' => 1,
                    'order_id' => $order->id,
                    'des' => '取消[美团]跑腿订单',
                ]);
                Log::info($log_prefix . '订单状态不是0，并且订单已经有配送平台了，配送平台不是[美团]发起取消-成功');
                return json_encode(['code' => 0]);
            }

            // 判断状态逻辑
            // 美团跑腿状态【0：待调度，20：已接单，30：已取货，50：已送达，99：已取消】
            // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
            if ($status == 0) {
                // 待调度【待接单】
                if ($delivery) {
                    try {
                        $delivery->update(['track' => OrderDeliveryTrack::TRACK_STATUS_WAITING]);
                        OrderDeliveryTrack::create([
                            'order_id' => $delivery->order_id,
                            'wm_id' => $delivery->wm_id,
                            'delivery_id' => $delivery->id,
                            'status' => 20,
                            'status_des' => OrderDeliveryTrack::TRACK_STATUS_WAITING,
                            'description' => '',
                        ]);
                    } catch (\Exception $exception) {
                        Log::info("美团跑腿-待接单回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("美团跑腿-待接单回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                Log::info($log_prefix . '待接单');
                return json_encode(['code' => 0]);
            }

            if ($status == 20) {
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
                        Log::info("美团跑腿-接单回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("美团跑腿-接单回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // 已接单
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    Log::info($log_prefix . '接单回调(已接单)，订单状态不正确，不能操作接单');
                    return json_encode(['code' => 0]);
                }
                // 取消其它平台订单
                // 取消蜂鸟订单
                // if ($order->fn_status === 20 || $order->fn_status === 30) {
                //     $fengniao = app("fengniao");
                //     $result = $fengniao->cancelOrder([
                //         'partner_order_code' => $order->order_id,
                //         'order_cancel_reason_code' => 2,
                //         'order_cancel_code' => 9,
                //         'order_cancel_time' => time() * 1000,
                //     ]);
                //     if ($result['code'] != 200) {
                //         // $logs = [
                //         //     "des" => "【美团订单回调】蜂鸟待接单取消失败",
                //         //     "id" => $order->id,
                //         //     "order_id" => $order->order_id
                //         // ];
                //         // $dd->sendMarkdownMsgArray("【ERROR】蜂鸟待接单取消失败", $logs);
                //     }
                //     OrderLog::create([
                //         'ps' => 2,
                //         'order_id' => $order->id,
                //         'des' => '取消【蜂鸟】跑腿订单',
                //     ]);
                //     Log::info($log_prefix . '取消蜂鸟待接单订单成功');
                // }
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
                // if ($order->mqd_status === 20 || $order->mqd_status === 30) {
                //     $meiquanda = app("meiquanda");
                //     $result = $meiquanda->repealOrder($order->mqd_order_id);
                //     if ($result['code'] != 100) {
                //         // $logs = [
                //         //     "des" => "【达达订单回调】美全达待接单取消失败",
                //         //     "id" => $order->id,
                //         //     "order_id" => $order->order_id
                //         // ];
                //         // $dd->sendMarkdownMsgArray("【ERROR】美全达待接单取消失败", $logs);
                //     }
                //     OrderLog::create([
                //         'ps' => 4,
                //         'order_id' => $order->id,
                //         'des' => '取消【美全达】跑腿订单',
                //     ]);
                //     Log::info($log_prefix . '取消美全达待接单订单成功');
                // }
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
                    DB::transaction(function () use ($order, $data, $longitude, $latitude, $delivery) {
                        OrderDelivery::where('id', $delivery->id)->update([
                            'delivery_name' => $data['courier_name'],
                            'delivery_phone' => $data['courier_phone'],
                            'delivery_lng' => $longitude,
                            'delivery_lat' => $latitude,
                            'is_payment' => 1,
                            'status' => 50,
                            'paid_at' => date("Y-m-d H:i:s"),
                            'arrival_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_RECEIVING,
                        ]);
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
                            "des" => "[美团]跑腿，待取货",
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
                // 到店、取货 足迹记录
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $longitude,
                            'delivery_lat' => $latitude,
                            'status' => 60,
                            'atshop_at' => date("Y-m-d H:i:s"),
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
                                'delivery_lng' => $longitude,
                                'delivery_lat' => $latitude,
                                'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_DELIVERING,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("美团跑腿-取货回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("美团跑腿-取货回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
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
                    "des" => "[美团]跑腿，配送中",
                    'name' => $data['courier_name'] ?? '',
                    'phone' => $data['courier_phone'] ?? '',
                ]);
                Log::info($log_prefix . '配送中，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                return json_encode(['code' => 0]);
            } elseif ($status == 50) {
                // 写入完成足迹
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $order->receiver_lng,
                            'delivery_lat' => $order->receiver_lat,
                            'status' => 70,
                            'finished_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                        ]);
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $delivery->id,
                                'status' => 70,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                            ], [
                                'order_id' => $delivery->order_id,
                                'wm_id' => $delivery->wm_id,
                                'delivery_id' => $delivery->id,
                                'status' => 70,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $order->receiver_lng,
                                'delivery_lat' => $order->receiver_lat,
                                'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_FINISH,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("美团跑腿-送达回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("美团跑腿-送达回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
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
                    "des" => "[美团]跑腿，已送达",
                    'name' => $data['courier_name'] ?? '',
                    'phone' => $data['courier_phone'] ?? '',
                ]);
                Log::info($log_prefix . '已完成，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                return json_encode(['code' => 0]);
            } elseif ($status == 99) {
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
                        Log::info("美团跑腿-取消回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("美团跑腿-取消回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // 已取消
                $reason_code = $request->get('cancel_reason_id', 0);
                if ($order->status >= 20 && $order->status < 70 && $order->ps == 1) {
                    try {
                        DB::transaction(function () use ($order, $data, $log_prefix, $reason_code) {
                            // if ($order->status == 50 || $order->status == 60) {
                            //     // 查询当前用户，做余额日志
                            //     $current_user = DB::table('users')->find($order->user_id);
                            //     UserMoneyBalance::create([
                            //         "user_id" => $order->user_id,
                            //         "money" => $order->money,
                            //         "type" => 1,
                            //         "before_money" => $current_user->money,
                            //         "after_money" => ($current_user->money + $order->money),
                            //         "description" => "取消美团跑腿订单：" . $order->order_id,
                            //         "tid" => $order->id
                            //     ]);
                            //     // 将配送费返回
                            //     DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                            //     Log::info($log_prefix . '接口取消订单，将钱返回给用户');
                            // }
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
                                'des' => '[美团]跑腿，发起取消配送',
                                'name' => $data['courier_name'] ?? '',
                                'phone' => $data['courier_phone'] ?? '',
                            ]);
                            if (in_array(in_array($order->zb_status, [0,1,3,7,80,99]) && $order->fn_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->uu_status, [0,1,3,7,80,99])) {
                                if (in_array($reason_code, [1201,1202,1203,1299,1399])) {
                                    dispatch(new CreateMtOrder($order, 2));
                                }
                            }
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        Log::info($log_prefix . '接口取消订单，将钱返回给用户失败', $message);
                        // $logs = [
                        //     "des" => "【美团订单回调】更改信息、将钱返回给用户失败",
                        //     "id" => $order->id,
                        //     "order_id" => $order->order_id
                        // ];
                        // $dd->sendMarkdownMsgArray("美团接口取消订单将钱返回给用户失败", $logs);
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
