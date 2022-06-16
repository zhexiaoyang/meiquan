<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Jobs\MtLogisticsSync;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\UserMoneyBalance;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ShunFengOrderController extends Controller
{
    use LogTool, NoticeTool;

    public $prefix_title = '[顺丰服务商订单回调&###]';

    public function order(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
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
        $status = $request->get("order_status", "");
        // 定义日志格式
        $this->prefix = str_replace('###', "订单状态&中台单号:{$order_id},状态:{$status}", $this->prefix_title);
        $this->log_info('全部参数', $request->all());

        if (in_array($status, [10, 15])) {
            $this->log_info("顺丰配送员坐标|order_id:{$order_id},status:{$status},lng:{$rider_lng}，lat:{$rider_lat}");
        }

        if ($order = Order::where('delivery_id', $order_id)->first()) {
            // 日志前缀
            $this->log_info("中台订单状态：{$order->status}");

            // 判断状态
            if ($order->status == 99) {
                $this->log_info("订单已是取消状态");
                return json_encode($res);
            }
            if ($order->status == 70) {
                $this->log_info("订单已是完成状态");
                return json_encode($res);
            }

            // 钉钉报警提醒
            $dingding = app("ding");

            // 如果状态不是 0 ，并且订单已经有配送平台了，配送平台不是[顺丰]发起取消
            if (($order->status > 30) && ($order->status < 70) && ($order->ps !== 7)) {
                $this->log_info("订单状态不是0，并且订单已经有配送平台了，配送平台不是「顺丰」发起取消-开始");
                $sf = app("shunfengservice");
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] != 0) {
                    $this->log_info("订单状态不是0，并且订单已经有配送平台了，配送平台不是「顺丰」发起取消-失败", [$result]);
                    $this->ding_error("订单状态不是0，并且订单已经有配送平台了，配送平台不是「顺丰」发起取消-失败");
                    return [];
                }
                // 记录订单日志
                OrderLog::create([
                    'ps' => 7,
                    "order_id" => $order->id,
                    "des" => "取消[顺丰]跑腿订单",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                $this->log_info("订单状态不是0，并且订单已经有配送平台了，配送平台不是「顺丰」发起取消-成功");
                return json_encode($res);
            }

            // 回调状态判断
            // 10-配送员确认;12:配送员到店;15:配送员配送中
            if ($status == 10) {
                $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 3);
                if (!$jiedan_lock->get()) {
                    // 获取锁定5秒...
                    $this->ding_error("[顺丰]派单后接单了,id:{$order->id},order_id:{$order->order_id},status:{$order->status}");
                    sleep(1);
                }
                // 配送员确认
                // 判断订单状态，是否可接单
                if ($order->status != 20 && $order->status != 30) {
                    $this->log_info('接单回调，订单状态不正确，不能操作接单');
                    return json_encode($res);
                }
                // 设置锁，防止其他平台接单
                if (!Redis::setnx("callback_order_id_" . $order->id, $order->id)) {
                    $this->log_info('设置锁失败');
                    return [];
                }
                Redis::expire("callback_order_id_" . $order->id, 6);
                // 取消其它平台订单
                if (($order->mt_status > 30) || ($order->fn_status > 30) || ($order->ss_status > 30) || ($order->mqd_status > 30) || ($order->uu_status > 30) || ($order->dd_status > 30)) {
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
                if ($order->fn_status === 20 || $order->fn_status === 30) {
                    $fengniao = app("fengniao");
                    $result = $fengniao->cancelOrder([
                        'partner_order_code' => $order->order_id,
                        'order_cancel_reason_code' => 2,
                        'order_cancel_code' => 9,
                        'order_cancel_time' => time() * 1000,
                    ]);
                    if ($result['code'] != 200) {
                        $this->log_info('蜂鸟待接单取消失败');
                    }
                    // 记录订单日志
                    OrderLog::create([
                        'ps' => 2,
                        "order_id" => $order->id,
                        "des" => "取消【蜂鸟】跑腿订单",
                    ]);
                    $this->log_info('取消蜂鸟待接单订单成功');
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
                        $this->log_info('闪送待接单取消失败');
                    }
                    OrderLog::create([
                        'ps' => 3,
                        'order_id' => $order->id,
                        'des' => '取消【闪送】跑腿订单',
                    ]);
                    $this->log_info('取消闪送待接单订单成功');
                }
                // 取消美全达订单
                if ($order->mqd_status === 20 || $order->mqd_status === 30) {
                    $meiquanda = app("meiquanda");
                    $result = $meiquanda->repealOrder($order->mqd_order_id);
                    if ($result['code'] != 100) {
                        $this->log_info('美全达待接单取消失败');
                    }
                    OrderLog::create([
                        'ps' => 4,
                        'order_id' => $order->id,
                        'des' => '取消【美全达】跑腿订单',
                    ]);
                    $this->log_info('取消美全达待接单订单成功');
                }
                // 取消达达订单
                if ($order->dd_status === 20 || $order->dd_status === 30) {
                    if ($order->shipper_type_dd) {
                        $config = config('ps.dada');
                        $config['source_id'] = get_dada_source_by_shop($order->shop_id);
                        $dada = new DaDaService($config);
                    } else {
                        $dada = app("dada");
                    }
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] != 0) {
                        $this->log_info('达达待接单取消失败');
                    }
                    OrderLog::create([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '取消【达达】跑腿订单',
                    ]);
                    $this->log_info('取消达达待接单订单成功');
                }
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
                    $this->log_info('取消UU待接单订单成功');
                }

                // 更改信息，扣款
                try {
                    DB::transaction(function () use ($order, $name, $phone, $rider_lng, $rider_lat) {
                        // 更改订单信息
                        Order::where("id", $order->id)->update([
                            'ps' => 7,
                            'money' => $order->money_sf,
                            'profit' => 1,
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
                        // $current_user = DB::table('users')->find($order->user_id);
                        // // 减去用户配送费
                        // DB::table('users')->where('id', $order->user_id)->decrement('money', $order->money_sf);
                        // // 用户余额日志
                        // // DB::table("user_money_balances")->insert();
                        // UserMoneyBalance::create([
                        //     "user_id" => $order->user_id,
                        //     "money" => $order->money_sf,
                        //     "type" => 2,
                        //     "before_money" => $current_user->money,
                        //     "after_money" => ($current_user->money - $order->money_sf),
                        //     "description" => "顺丰跑腿订单：" . $order->order_id,
                        //     "tid" => $order->id
                        // ]);
                        // 记录订单日志
                        OrderLog::create([
                            'ps' => 7,
                            "order_id" => $order->id,
                            "des" => "[顺丰]跑腿，待取货",
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    });
                    $this->log_info('顺丰接单，更改信息成功');
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
                $order = Order::where('delivery_id', $order_id)->first();
                dispatch(new MtLogisticsSync($order));
                return json_encode($res);
            }elseif ($status == 15) {
                // 10-配送员确认;12:配送员到店;15:配送员配送中
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
                    "des" => "[顺丰]跑腿，配送中",
                    'name' => $name,
                    'phone' => $phone,
                ]);
                dispatch(new MtLogisticsSync($order));
                $this->log_info('取件成功，配送中，更改信息成功');
                return json_encode($res);
            }
        }

        return json_encode($res);
    }

    public function complete(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        // Log::info('顺丰跑腿回调-订单完成回调-全部参数', $request->all());
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
        $status = $request->get("order_status", "");
        // 定义日志格式
        $this->prefix = str_replace('###', "完成订单&中台单号:{$order_id},状态:{$status}", $this->prefix_title);
        $this->log_info('全部参数', $request->all());
        Log::info("顺丰配送员坐标|order_id:{$order_id}，status:{$status}", ['lng' => $rider_lng, 'lat' => $rider_lat]);

        $receipt_type = $request->get("receipt_type", 1);

        if ($order = Order::where('delivery_id', $order_id)->first()) {
            // 日志前缀
            $this->log_info("中台订单状态：{$order->status}");
            // 判断状态
            if ($order->status == 99) {
                $this->log_info("订单已是取消状态");
                return json_encode($res);
            }
            if ($order->status == 70) {
                $this->log_info("订单已是完成状态");
                return json_encode($res);
            }

            if ($receipt_type != 1) {
                $this->ding_error("顺丰签收类型：商家退回签收|id:{$order->id},order_id:{$order->order_id}");
                return json_encode($res);
            }

            $order->status = 70;
            $order->sf_status = 70;
            $order->over_at = date("Y-m-d H:i:s");
            $order->courier_name = $name;
            $order->courier_phone = $phone;
            $order->courier_lng = $order->receiver_lng;
            $order->courier_lat = $order->receiver_lat;
            $order->pay_status = 1;
            $order->pay_at = date("Y-m-d H:i:s");
            $order->save();
            // 记录订单日志
            OrderLog::create([
                'ps' => 7,
                "order_id" => $order->id,
                "des" => "[顺丰]跑腿，已送达",
                'name' => $name,
                'phone' => $phone,
            ]);
            $this->log_info('配送完成，更改信息成功');
            dispatch(new MtLogisticsSync($order));
            // 查找扣款用户，为了记录余额日志
            $current_user = DB::table('users')->find($order->user_id);
            // 减去用户配送费
            // 服务费
            $service_fee = 0.2;
            DB::table('users')->where('id', $order->user_id)->decrement('money', $service_fee);
            // 用户余额日志
            UserMoneyBalance::create([
                "user_id" => $order->user_id,
                "money" => $service_fee,
                "type" => 2,
                "before_money" => $current_user->money,
                "after_money" => ($current_user->money - $service_fee),
                "description" => "顺丰跑腿订单服务费：" . $order->order_id,
                "tid" => $order->id
            ]);
            $this->log_info('配送完成，扣款成功');
        }

    }

    public function cancel(Request $request)
    {
        $res = ["error_code" => 0, "error_msg" => "success"];
        Log::info('顺丰跑腿回调-订单取消回调-全部参数', $request->all());
        // 商家订单ID
        $order_id = $request->get("shop_order_id", "");
        // 定义日志格式
        $this->prefix = str_replace('###', "取消订单&中台单号:{$order_id}", $this->prefix_title);
        $this->log_info('全部参数', $request->all());

        if ($order = Order::where('delivery_id', $order_id)->first()) {
            // 日志前缀
            $this->log_info("中台订单状态：{$order->status}");
            // 判断状态
            if ($order->status == 99) {
                $this->log_info("订单已是取消状态");
                return json_encode($res);
            }
            if ($order->status == 70) {
                $this->log_info("订单已是完成状态");
                return json_encode($res);
            }

            if ($order->status >= 20 && $order->status < 70 ) {
                try {
                    DB::transaction(function () use ($order) {
                        $update_data = [
                            'sf_status' => 99
                        ];
                        if (in_array($order->mt_status, [0,1,3,7,80,99]) && in_array($order->fn_status, [0,1,3,7,80,99]) &&
                            in_array($order->ss_status, [0,1,3,7,80,99]) && in_array($order->mqd_status, [0,1,3,7,80,99]) &&
                            in_array($order->uu_status, [0,1,3,7,80,99]) && in_array($order->dd_status, [0,1,3,7,80,99])) {
                            $update_data = [
                                'status' => 99,
                                'sf_status' => 99
                            ];
                        }
                        Order::where("id", $order->id)->update($update_data);
                        OrderLog::create([
                            'ps' => 5,
                            'order_id' => $order->id,
                            'des' => '「顺丰」跑腿，发起取消配送',
                        ]);
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

        return json_encode($res);
    }

    public function revoke(Request $request)
    {
        \Log::info("顺丰revoke", $request->all());
        $res = ["error_code" => 0, "error_msg" => "success"];
        return json_encode($res);
    }
}
