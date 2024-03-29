<?php

namespace App\Http\Controllers\Api\Callback;

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
use App\Models\UserMoneyBalance;
use App\Traits\RiderOrderCancel;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DaDaOrderController
{
    use RiderOrderCancel;

    public $prefix_title = '[达达服务商订单回调&###]';

    public function order(Request $request)
    {
        $res = ['status' => 'ok'];
        // 接收全部参数
        $data = $request->all();
        if (empty($data)) {
            return $res;
        }
        // 商家订单号
        $order_id = $data['order_id'] ?? '';
        // 订单状态(待接单＝1,待取货＝2,配送中＝3,已完成＝4,已取消＝5, 指派单=8,妥投异常之物品返回中=9, 妥投异常之物品返回完成=10, 骑士到店=100,
        // 创建达达运单失败=1000 可参考文末的状态说明）
        $status = $data['order_status'] ?? '';
        // 重复回传状态原因
        $repeat_reason_type = $data['repeat_reason_type'] ?? 0;
        // if ($repeat_reason_type) {
            // $this->ding_error("{$order_id}:重复回传状态原因:{$repeat_reason_type}");
        // }
        // 配送员姓名
        $name = $data['dm_name'] ?? '';
        // 配送员手机号
        $phone = $data['dm_mobile'] ?? '';
        $phone = str_replace('-', '-', $phone);
        $longitude = '';
        $latitude = '';
        $locations = ['lng' => '', 'lat' => ''];
        $cancel_from = $data['cancel_from'] ?? 2;
        // 定义日志格式
        $this->notice_tool2_prefix = str_replace('###', "中台单号:{$order_id},状态:{$status}", $this->prefix_title);
        $this->log_info('全部参数', $data);
        // if ($status === 1) {
        //     return json_encode($res);
        // }
        // if (intval($cancel_from) === 2) {
        //     $this->log_info('商家主动取消，不进行操作');
        //     return json_encode($res);
        // }

        // 查找订单
        if ($order = Order::where('order_id', $order_id)->first()) {
            // 如果是接单状态，设置接单锁
            if ($status == 2) {
                $_time1 = time();
                try {
                    // 获取接单状态锁，如果锁存在，等待8秒
                    Cache::lock("jiedan_lock:{$order->id}", 3)->block(8);
                    // 获取锁成功
                } catch (LockTimeoutException $e) {
                    // 获取锁失败
                    $this->ding_error("自有达达|接单获取锁失败错误|{$order->id}|{$order->order_id}：" . json_encode($request->all(), JSON_UNESCAPED_UNICODE));
                }
                $_time2 = time();
                // 重新查找订单，防止锁之前更换状态
                if ($_time1 !== $_time2) {
                    $order = Order::find($order->id);
                }
            }
            // 跑腿运力
            $delivery = OrderDelivery::where('three_order_no', $order_id)->where('platform', 5)->where('status', '<=', 70)->orderByDesc('id')->first();
            // 重复回传状态原因-重新分配骑士:取消订单
            if ($repeat_reason_type == 1) {
                $config = config('ps.dada');
                $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                $dada_app = new DaDaService($config);
                $result = $dada_app->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    // 重复回传状态原因-重新分配骑士:取消订单成功
                    $this->ding_error("达达自有{$order_id}:重复回传状态原因:{$repeat_reason_type}|取消达达订单成功");
                    OrderLog::create([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '「达达」骑手取消跑腿订单',
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
                    $order->fail_sf = '';

                    $order->zb_status = 0;
                    $order->fail_zb = '';

                    $order->status = 8;
                    $order->ps = 0;
                    $order->shipper_type_ss = 0;
                    $order->shipper_type_dd = 0;
                    $order->shipper_type_sf = 0;
                    $order->save();
                    event(new OrderCancel($order->id, 5));
                    $order = Order::find($order->id);
                    dispatch(new CreateMtOrder($order));
                    OrderLog::create([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '「达达」骑手取消跑腿订单，重新派单',
                    ]);
                } else {
                    // 重复回传状态原因-重新分配骑士:取消订单失败
                    $this->ding_error("达达自有{$order_id}:重复回传状态原因:{$repeat_reason_type}|取消达达订单失败");
                }
                return json_encode($res);
            }
            if ($repeat_reason_type == 2) {
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'status' => 50,
                            'track' => OrderDeliveryTrack::TRACK_STATUS_TRANSFER,
                        ]);
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $delivery->id,
                                'status' => 50,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_TRANSFER,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                            ], [
                                'order_id' => $delivery->order_id,
                                'wm_id' => $delivery->wm_id,
                                'delivery_id' => $delivery->id,
                                'status' => 50,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_TRANSFER,
                                'delivery_name' => $name,
                                'delivery_phone' => $phone,
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                                'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_TRANSFER,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("达达自有-转单回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("达达自有-转单回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // 转单
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $longitude;
                $order->courier_lat = $latitude;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 5,
                    "order_id" => $order->id,
                    "des" => "[达达]跑腿，转单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            }
            $this->log_info("中台订单状态：{$order->status}");
            // 获取达达配送员坐标
            // 达达订单状态(待接单＝1,待取货＝2,配送中＝3,已完成＝4,已取消＝5, 指派单=8,妥投异常之物品返回中=9, 妥投异常之物品返回完成=10,
            if (in_array($status, [2,3])) {
                $config = config('ps.dada');
                $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                $dada_app = new DaDaService($config);
                $dada_info = $dada_app->getOrderInfo($order_id);
                $longitude = $dada_info['result']['transporterLng'] ?? '';
                $latitude = $dada_info['result']['transporterLat'] ?? '';
                $locations = ['lng' => $longitude, 'lat' => $latitude];
                $this->log_info("达达配送员坐标|lng:{$longitude},lat:{$latitude}");
            }

            if ($order->status == 99) {
                $this->log_info("订单已是取消状态");
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
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("自有达达-取消回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-取消回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                return json_encode($res);
            }
            if ($order->status == 70) {
                $this->log_info("订单已是完成");
                return json_encode($res);
            }

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是「达达」发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 5) && ($status != 5)) {
                $this->log_info("订单状态不是0，并且订单已经有配送平台了，配送平台不是「达达」发起取消-开始");
                $config = config('ps.dada');
                $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                $dada_app = new DaDaService($config);
                $result = $dada_app->orderCancel($order->order_id);
                if ( ($result['code'] != 0) && (strstr($result['msg'], '已取消') === false) ) {
                    $this->log_info("订单状态不是0，并且订单已经有配送平台了，配送平台不是「达达」发起取消-失败", [$result]);
                    $this->ding_error("订单状态不是0，并且订单已经有配送平台了，配送平台不是「达达」发起取消-失败");
                    return ['status' => 'err'];
                }
                if ($result['msg'] == '订单已取消,无法重复取消') {
                    return ['status' => 'false'];
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 5,
                    "order_id" => $order->id,
                    "des" => "取消「达达」跑腿订单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                $this->log_info("订单状态不是0，并且订单已经有配送平台了，配送平台不是「达达」发起取消-成功");
                return json_encode($res);
            }
            // 达达订单状态(待接单＝1,待取货＝2,配送中＝3,已完成＝4,已取消＝5, 指派单=8,妥投异常之物品返回中=9, 妥投异常之物品返回完成=10,
            // 骑士到店=100,创建达达运单失败=1000 可参考文末的状态说明）
            // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
            if ($status == 1) {
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
                        Log::info("自有达达-待接单回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-待接单回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
            } elseif ($status == 2) {
                // 写入接单足迹
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'status' => 50,
                            'arrival_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_RECEIVING,
                        ]);
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
                        Log::info("自有达达-接单回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-接单回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                // if (!$jiedan_lock->get()) {
                //     // 获取锁定5秒...
                //     $this->ding_error("[达达]派单后接单了,id:{$order->id},order_id:{$order->order_id},status:{$order->status}");
                //     sleep(1);
                // }
                // 取货中
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    $this->log_info('接单回调，订单状态不正确，不能操作接单');
                    return json_encode($res);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    $this->log_info('设置锁失败');
                    return ['status' => 'err'];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30) || ($order->ss_status > 30) || ($order->mqd_status > 30) || ($order->uu_status > 30) || ($order->sf_status > 30)) {
                    $this->log_info('取消其它平台订单');
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
                        $this->log_info('美团待接单取消失败');
                    }
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 1,
                        "order_id" => $order->id,
                        "des" => "取消【美团】跑腿订单",
                    ]);
                    $this->log_info('取消美团待接单订单成功');
                }
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
                //         $this->log_info('蜂鸟待接单取消失败');
                //     }
                //     // 记录订单日志
                //     OrderLog::create([
                //         'ps' => 2,
                //         "order_id" => $order->id,
                //         "des" => "取消【蜂鸟】跑腿订单",
                //     ]);
                //     $this->log_info('取消蜂鸟待接单订单成功');
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
                        $this->log_info('闪送待接单取消失败');
                    }
                    OrderLog::create([
                        'ps' => 3,
                        'order_id' => $order->id,
                        'des' => '取消【闪送】跑腿订单',
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 3, '自有达达');
                    $this->log_info('取消闪送待接单订单成功');
                }
                // 取消美全达订单
                // if ($order->mqd_status === 20 || $order->mqd_status === 30) {
                //     $meiquanda = app("meiquanda");
                //     $result = $meiquanda->repealOrder($order->mqd_order_id);
                //     if ($result['code'] != 100) {
                //         $this->log_info('美全达待接单取消失败');
                //     }
                //     OrderLog::create([
                //         'ps' => 4,
                //         'order_id' => $order->id,
                //         'des' => '取消【美全达】跑腿订单',
                //     ]);
                //     $this->log_info('取消美全达待接单订单成功');
                // }
                // 取消UU订单
                if ($order->uu_status === 20 || $order->uu_status === 30) {
                    $uu = app("uu");
                    $result = $uu->cancelOrder($order);
                    if ($result['return_code'] != 'ok') {
                        $this->log_info('UU待接单取消失败');
                    }
                    OrderLog::create([
                        'ps' => 6,
                        'order_id' => $order->id,
                        'des' => '取消【UU跑腿】订单',
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 6, '自有达达');
                    $this->log_info('取消UU待接单订单成功');
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
                        $this->log_info('顺丰待接单取消失败');
                    }
                    OrderLog::create([
                        'ps' => 7,
                        'order_id' => $order->id,
                        'des' => '取消【顺丰】跑腿订单',
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 7, '自有达达');
                    // // 顺丰跑腿运力
                    // $sf_delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                    // // 写入顺丰取消足迹
                    // if ($sf_delivery) {
                    //     try {
                    //         $sf_delivery->update([
                    //             'status' => 99,
                    //             'cancel_at' => date("Y-m-d H:i:s"),
                    //             'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    //         ]);
                    //         OrderDeliveryTrack::firstOrCreate(
                    //             [
                    //                 'delivery_id' => $sf_delivery->id,
                    //                 'status' => 99,
                    //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    //             ], [
                    //                 'order_id' => $sf_delivery->order_id,
                    //                 'wm_id' => $sf_delivery->wm_id,
                    //                 'delivery_id' => $sf_delivery->id,
                    //                 'status' => 99,
                    //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    //             ]
                    //         );
                    //     } catch (\Exception $exception) {
                    //         Log::info("自有达达-接单回调取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    //         $this->ding_error("自有达达-接单回调取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    //     }
                    // }
                    $this->log_info('取消顺丰待接单订单成功');
                }
                // 取消众包跑腿
                if ($order->zb_status === 20 || $order->zb_status === 30) {
                    $this->cancelRiderOrderMeiTuanZhongBao($order, 8);
                }
                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone, $longitude, $latitude) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 5,
                            'money' => $order->money_dd,
                            'profit' => 0,
                            'status' => 50,
                            'dd_status' => 50,
                            'mt_status' => $order->mt_status < 20 ?: 7,
                            'fn_status' => $order->fn_status < 20 ?: 7,
                            'ss_status' => $order->ss_status < 20 ?: 7,
                            'mqd_status' => $order->mqd_status < 20 ?: 7,
                            'uu_status' => $order->uu_status < 20 ?: 7,
                            'sf_status' => $order->sf_status < 20 ?: 7,
                            'receive_at' => date("Y-m-d H:i:s"),
                            'peisong_id' => $order->dd_order_id,
                            'courier_name' => $name,
                            'courier_phone' => $phone,
                            'courier_lng' => $longitude,
                            'courier_lat' => $latitude,
                            'pay_status' => 1,
                            'pay_at' => date("Y-m-d H:i:s"),
                        ]);
                        // // 查找扣款用户，为了记录余额日志
                        // $current_user = DB::table('users')->find($order->user_id);
                        // // 减去用户配送费
                        // DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_dd);
                        // // 用户余额日志
                        // // DB::table("user_money_balances")->insert();
                        // UserMoneyBalance::create([
                        //     "user_id" => $order->user_id,
                        //     "money" => $order->money_dd,
                        //     "type" => 2,
                        //     "before_money" => $current_user->money,
                        //     "after_money" => ($current_user->money - $order->money_dd),
                        //     "description" => "达达跑腿订单：" . $order->order_id,
                        //     "tid" => $order->id
                        // ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 5,
                            "order_id" => $order->id,
                            "des" => "「达达」跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    $this->log_info('达达接单，更改信息成功');
                } catch (\Exception $e) {
                    $message = [
                        $e->getCode(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getMessage()
                    ];
                    $this->log_info('更改信息事务提交失败', $message);
                    $this->ding_error("更改信息事务提交失败");
                    return ['code' => 'error'];
                }
                // 同步美团外卖配送信息
                $order = Order::where('order_id', $order_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            } elseif ($status == 100) {
                if ($delivery) {
                    try {
                        $delivery->update([
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'status' => 50,
                            'atshop_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_PICKING,
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
                        Log::info("自有达达-到店回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-到店回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                return json_encode($res);
            } elseif ($status == 3) {
                // 达达订单状态(待接单＝1,待取货＝2,配送中＝3,已完成＝4,已取消＝5, 指派单=8,妥投异常之物品返回中=9, 妥投异常之物品返回完成=10,
                // 骑士到店=100,创建达达运单失败=1000 可参考文末的状态说明）
                // 美全订单状态【20：待接单，30：待接单，40：待取货，50：待取货，60：配送中，70：已完成，99：已取消】
                // 到店、取货 足迹记录
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
                        Log::info("自有达达-取货回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-取货回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // 送货中
                $order->status = 60;
                $order->dd_status = 60;
                $order->take_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $longitude;
                $order->courier_lat = $latitude;
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 5,
                    "order_id" => $order->id,
                    "des" => "「达达」跑腿，配送中",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                $this->log_info('取件成功，配送中，更改信息成功');
                return json_encode($res);
            } elseif ($status == 4) {
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
                            'track' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                            'is_payment' => 1,
                            'paid_at' => date("Y-m-d H:i:s"),
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
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                                'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_FINISH,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("自有达达-送达回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-送达回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                // 服务费
                $service_fee = 0.1;
                $order->status = 70;
                $order->dd_status = 70;
                $order->over_at = date("Y-m-d H:i:s");
                $order->courier_name = $name;
                $order->courier_phone = $phone;
                $order->courier_lng = $order->receiver_lng;
                $order->courier_lat = $order->receiver_lat;
                $order->pay_status = 1;
                $order->profit = $service_fee;
                $order->service_fee = $service_fee;
                $order->pay_at = date("Y-m-d H:i:s");
                $order->save();
                // 记录订单日志
                OrderLog::create([
                    'ps' => 5,
                    "order_id" => $order->id,
                    "des" => "「达达」跑腿，已送达",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                $this->log_info('配送完成，更改信息成功');
                dispatch(new MtLogisticsSync($order));
                // 查找扣款用户，为了记录余额日志
                $current_user = DB::table('users')->find($order->user_id);
                // 减去用户配送费
                DB::table('users')->where('id', $order->user_id)->decrement('money', $service_fee);
                // 用户余额日志
                UserMoneyBalance::create([
                    "user_id" => $order->user_id,
                    "money" => $service_fee,
                    "type" => 2,
                    "before_money" => $current_user->money,
                    "after_money" => ($current_user->money - $service_fee),
                    "description" => "达达跑腿订单服务费：" . $order->order_id,
                    "tid" => $order->id
                ]);
                $this->log_info('配送完成，扣款成功');
                event(new OrderComplete($order->id, $order->user_id, $order->shop_id, date("Y-m-d", strtotime($order->created_at))));
                return json_encode($res);
            } elseif ($status == 5) {
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
                                'delivery_lng' => $locations['lng'] ?? '',
                                'delivery_lat' => $locations['lat'] ?? '',
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("自有达达-取消回调-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-取消回调-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
                if ($order->status >= 20 && $order->status < 70 ) {
                    try {
                        DB::transaction(function () use ($order, $name, $phone, $cancel_from) {
                            $update_data = [
                                'dd_status' => 99
                            ];
                            if (in_array($order->zb_status, [0,1,3,7,80,99]) && in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->uu_status, [0,1,3,7,80,99])) {
                                $update_data = [
                                    'status' => 99,
                                    'dd_status' => 99
                                ];
                                event(new OrderCancel($order->id, 5));
                            }
                            Order::where("id", $order->id)->update($update_data);
                            OrderLog::create([
                                'ps' => 5,
                                'order_id' => $order->id,
                                'des' => '「达达」跑腿，发起取消配送',
                            ]);
                            if (in_array(in_array($order->zb_status, [0,1,3,7,80,99]) && $order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) && in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) && in_array($order->sf_status, [0,1,3,7,80,99]) && in_array($order->uu_status, [0,1,3,7,80,99])) {
                                if ($cancel_from === 1 || $cancel_from === 3) {
                                    $update_data = [
                                        'status' => 0,
                                        'dd_status' => 0,
                                        'ps' => 0
                                    ];
                                    Order::where("id", $order->id)->update($update_data);
                                    $this->log_info('达达发起取消配送，系统重新呼叫跑腿');
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
                        $this->log_info('取消订单事务提交失败', $message);
                        $this->ding_error('取消订单事务提交失败');
                        return json_encode(['code' => 100]);
                    }
                    $this->log_info('接口取消订单成功');
                } else {
                    $this->log_info("取消订单，状态不正确。状态(status)：{$order->status}");
                }
                return json_encode($res);
            }
        }
        return json_encode($res);
    }
}
