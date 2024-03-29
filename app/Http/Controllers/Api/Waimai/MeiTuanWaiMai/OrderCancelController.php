<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Jobs\WarehouseCancelOrderStockSync;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\Shop;
use App\Models\UserOperateBalance;
use App\Models\VipBillItem;
use App\Models\WmProduct;
use App\Task\TakeoutOrderVoiceNoticeTask;
use App\Traits\NoticeTool;
use App\Traits\UserMoneyAction;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\UserMoneyBalance;
use App\Models\WmOrder;
use App\Traits\LogTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCancelController
{
    use LogTool, NoticeTool, UserMoneyAction;

    public $prefix_title = '[美团外卖取消回调&###]';

    public function cancel(Request $request, $platform)
    {
        if (!$order_id = $request->get('order_id')) {
            return json_encode(["data" => "ok"]);
        }
        // 日志格式
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&订单号:{$order_id}", $this->prefix_title);
        // 查找外卖订单-更改外卖订单状态
        if ($wmOrder = WmOrder::where('order_id', $order_id)->first()) {
            if ($wmOrder->status <= 18) {
                $wmOrder->status = 30;
                $wmOrder->cancel_at = date("Y-m-d H:i:s");
                $wmOrder->save();
                $this->log_info("取消外卖订单-成功");
                // ****************************
                // *** 代运营服务费-处方费|返款 ***
                // ****************************
                // 1. 查询改该订单代运营服务费-扣费记录
                if ($decr_log = UserOperateBalance::where('order_id', $wmOrder->id)->where('type', 2)->where('type2', 3)->first()) {
                    // 2. 查询改订单代运营服务费-退款总和
                    $incr_total = UserOperateBalance::where('order_id', $wmOrder->id)->where('type', 1)->where('type2', 3)->sum('money');
                    // 3. 计算退款金额
                    $refund_money = (($decr_log->money * 100) - ($incr_total * 100)) / 100;
                    // 4. 操作退款
                    if ($refund_money > 0 && $refund_money <= $wmOrder->operate_service_fee) {
                        $description = "{$wmOrder->order_id}订单，代运营服务费返还";
                        $tui_res = $this->operateIncrement($wmOrder->user_id, $refund_money, $description, $wmOrder->shop_id, $wmOrder->id, 3, $wmOrder->id);
                        $this->log_info("退款状态", [$tui_res]);
                    }
                }
                // 2. 查询改该订单处方费费-扣费记录
                if ($prescription_decr_log = UserOperateBalance::where('order_id', $wmOrder->id)->where('type', 2)->where('type2', 2)->first()) {
                    // 2. 查询改订单代运营服务费-退款总和
                    $prescription_incr_total = UserOperateBalance::where('order_id', $wmOrder->id)->where('type', 1)->where('type2', 2)->sum('money');
                    // 3. 计算退款金额
                    $prescription_refund_money = (($prescription_decr_log->money * 100) - ($prescription_incr_total * 100)) / 100;
                    // 4. 操作退款
                    if ($prescription_refund_money > 0) {
                        $description = "{$wmOrder->order_id}订单，处方费返还";
                        $tui_res = $this->operateIncrement($wmOrder->user_id, $prescription_refund_money, $description, $wmOrder->shop_id, $wmOrder->id, 2, $wmOrder->id);
                        $this->log_info("退款状态", [$tui_res]);
                    }
                }
            } else {
                $this->log_info("外卖订单取消失败,外卖订单状态:{$wmOrder->status}");
            }
            // -------------------VIP订单结算----------------------
            if ($wmOrder->status == 18 && $wmOrder->is_vip && $shop = Shop::find($wmOrder->shop_id)) {
                // 处方审方扣费
                $prescription = $wmOrder->is_prescription ? 1.5 : 0;
                // 总利润
                $total = $wmOrder->vip_cost + $prescription - $wmOrder->poi_receive;
                // VIP门店各方利润百分比
                $commission = $shop->vip_commission;
                $commission_manager = $shop->vip_commission_manager;
                $commission_operate = $shop->vip_commission_operate;
                $commission_internal = $shop->vip_commission_internal;
                $business = 100 - $commission - $commission_manager - $commission_operate - $commission_internal;

                $vip_city = sprintf("%.2f",$total * $commission_manager / 100);
                $vip_operate = sprintf("%.2f", $total * $commission_operate / 100);
                $vip_internal = sprintf("%.2f",$total * $commission_internal / 100);
                $vip_business = sprintf("%.2f",$total * $business / 100);
                $vip_company = sprintf("%.2f",$total - $vip_operate - $vip_city - $vip_internal - $vip_business);
                $item = [
                    'order_id' => $wmOrder->id,
                    'shop_id' => $wmOrder->shop_id,
                    'order_no' => $wmOrder->order_id,
                    'platform' => $wmOrder->platform,
                    'app_poi_code' => $wmOrder->app_poi_code,
                    'wm_shop_name' => $wmOrder->wm_shop_name,
                    'day_seq' => $wmOrder->day_seq,
                    'trade_type' => 2,
                    'status' => $wmOrder->status,
                    'order_at' => $wmOrder->created_at,
                    'finish_at' => $wmOrder->finish_at,
                    'bill_date' => date("Y-m-d"),
                    'vip_settlement' => 0 - $wmOrder->poi_receive,
                    'vip_cost' => $wmOrder->vip_cost,
                    'vip_permission' => $prescription,
                    'vip_total' => $total,
                    'vip_commission_company' => $commission,
                    'vip_commission_manager' => $commission_manager,
                    'vip_commission_operate' => $commission_operate,
                    'vip_commission_internal' => $commission_internal,
                    'vip_commission_business' => $business,
                    'vip_company' => $vip_company,
                    'vip_city' => $vip_city,
                    'vip_operate' => $vip_operate,
                    'vip_internal' => $vip_internal,
                    'vip_business' => $vip_business,
                ];
                VipBillItem::create($item);
                \Log::info("VIP订单结算处理，取消订单结算成功");
                // Task::deliver(new TakeoutOrderVoiceNoticeTask(9, $wmOrder->user_id), true);
                Task::deliver(new TakeoutOrderVoiceNoticeTask(9, $shop->account_id ?: $shop->user_id), true);
            }
            // -------------------VIP订单结算-结束----------------------
            // 仓库库存
            if (WmProduct::where('shop_id', $wmOrder->shop_id)->first()) {
                dispatch(new WarehouseCancelOrderStockSync($wmOrder));
            }
        } else {
            $this->log_info("外卖订单不存在");
        }
        // 查找跑腿订单
        if (!$order = Order::query()->where('order_id', $order_id)->first()) {
            $this->log_info("跑腿订单不存在");
            return json_encode(["data" => "ok"]);
        }
        // $this->ding_exception("有取消订单了");
        // 当前配送平台
        $ps = $order->ps;
        // 判断状态
        if ($order->status == 99) {
            // 已经是取消状态
            return json_encode(["data" => "ok"]);
        } elseif ($order->status == 80) {
            // 异常状态
            return json_encode(["data" => "ok"]);
        } elseif ($order->status == 70) {
            // 已经完成
            return json_encode(["data" => "ok"]);
        } elseif (in_array($order->status, [40, 50, 60])) {
            $dd = app("ding");
            if ($ps == 1) {
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] === 0) {
                    $this->log_info("取消已接单美团跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->take_at)) {
                                $jian_money = $order->money;
                            }
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "[美团外卖]取消[美团跑腿]订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            if ($jian_money > 0) {
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "[美团外卖]取消[美团跑腿]订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                            }
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mt_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            $this->log_info("取消已接单美团跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[美团跑腿]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单美团跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单美团跑腿订单失败");
                    $this->ding_error("取消已接单美团跑腿订单失败");
                }
            } elseif ($ps == 2) {
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] == 200) {
                    $this->log_info("取消已接单蜂鸟跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->receive_at)) {
                                $jian = time() - strtotime($order->receive_at);
                                if ($jian <= 1200) {
                                    $jian_money = 2;
                                }
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                            }
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "[美团外卖]取消[蜂鸟]订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            if ($jian_money > 0) {
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "[美团外卖]取消[蜂鸟]订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                            }
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'fn_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            $this->log_info("取消已接单蜂鸟跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[蜂鸟]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单蜂鸟跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单蜂鸟跑腿订单失败");
                    $this->ding_error("取消已接单蜂鸟跑腿订单失败");
                }
            } elseif ($ps == 3) {
                if ($order->shipper_type_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                } else {
                    $shansong = app("shansong");
                }
                $result = $shansong->cancelOrder($order->ss_order_id);
                if (($result['status'] == 200) || ($result['msg'] = '订单已经取消')) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 3, '美团外卖聚合');
                    $this->log_info("取消已接单闪送跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
                            if ($order->shipper_type_ss == 0) {
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->receive_at)) {
                                    $jian_money = 2;
                                    $jian = time() - strtotime($order->receive_at);
                                    if ($jian >= 480) {
                                        $jian_money = 5;
                                    }
                                    if (!empty($order->take_at)) {
                                        $jian_money = 5;
                                    }
                                }

                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "[美团外卖]取消[闪送]订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "[美团外卖]取消[闪送]订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                $this->log_info("取消已接单闪送跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-自主注册闪送，取消不扣款");
                            }
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'ss_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[闪送]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单闪送跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单闪送跑腿订单失败");
                    $this->ding_error("取消已接单闪送跑腿订单失败");
                }
            } elseif ($ps == 4) {
                $fengniao = app("meiquanda");
                $result = $fengniao->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    $this->log_info("取消已接单美全达跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "[美团外卖]取消[美全达]订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mqd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            DB::table('users')->where('id', $order->user_id)->increment('money', $order->money);
                            $this->log_info("取消已接单美全达跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money}");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[美全达]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单美全达跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单美全达跑腿订单失败");
                    $this->ding_error("取消已接单美全达跑腿订单失败");
                }
            } elseif ($ps == 5) {
                if ($order->shipper_type_dd) {
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    $dada = app("dada");
                }
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 5, '美团外卖聚合');
                    $this->log_info("取消已接单达达跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
                            if ($order->shipper_type_dd == 0) {
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->receive_at)) {
                                    $jian = time() - strtotime($order->receive_at);
                                    if ($jian >= 60 && $jian <= 900) {
                                        $jian_money = 2;
                                    }
                                }
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "[美团外卖]取消[达达]订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::query()->create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "[美团外卖]取消[达达]订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                $this->log_info("取消已接单达达跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-自主注册不扣款");
                            }
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'dd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[达达]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单达达跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单达达跑腿订单失败");
                    $this->ding_error("取消已接单达达跑腿订单失败");
                }
            } elseif ($ps == 6) {
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    $this->log_info("取消已接单UU跑腿订单成功");
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 6, '美团外卖聚合');
                    try {
                        DB::transaction(function () use ($order) {
                            // 用户余额日志
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->take_at)) {
                                $jian_money = 3;
                            }
                            // 当前用户
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "[美团外卖]取消[UU]订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $jian_money,
                                "type" => 2,
                                "before_money" => ($current_user->money + $order->money),
                                "after_money" => ($current_user->money + $order->money - $jian_money),
                                "description" => "[美团外卖]取消[UU]订单扣款：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'uu_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            $this->log_info("取消已接单UU跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[UU]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单UU跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "UU",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单UU跑腿订单失败");
                    $this->ding_error("取消已接单UU跑腿订单失败");
                }
            } elseif ($ps == 7) {
                if ($order->shipper_type_sf) {
                    $sf = app("shunfengservice");
                } else {
                    $sf = app("shunfeng");
                }
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 7, '美团外卖聚合');
                    $this->log_info("取消已接单顺丰跑腿订单成功");
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
                    //         Log::info("饿了么取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    //         $this->ding_error("饿了么取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    //     }
                    // }
                    try {
                        DB::transaction(function () use ($order, $result) {
                            if ($order->shipper_type_sf == 0) {
                                // 用户余额日志
                                // 计算扣款
                                $jian_money = isset($result['result']['deduction_detail']['deduction_fee']) ? ($result['result']['deduction_detail']['deduction_fee']/100) : 0;
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-扣款金额：{$jian_money}");
                                // 当前用户
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "[美团外卖]取消[顺丰]订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::query()->create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "[美团外卖]取消[顺丰]订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                $this->log_info("取消已接单顺丰跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-自主注册顺丰，取消不扣款");
                            }
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'sf_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "[美团外卖]取消[顺丰]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        $this->log_info("取消已接单顺丰跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "顺丰",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    $this->log_info("取消已接单顺丰跑腿订单失败");
                    $this->ding_error("取消已接单顺丰跑腿订单失败");
                }
            }
            return json_encode(["data" => "ok"]);
        } elseif (in_array($order->status, [20, 30])) {
            // 没有骑手接单，取消订单
            if (in_array($order->mt_status, [20, 30])) {
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->mt_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[美团跑腿]订单"
                    ]);
                } else {
                    $this->ding_error("取消美团订单失败");
                }
            }
            if (in_array($order->fn_status, [20, 30])) {
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] == 200) {
                    $order->status = 99;
                    $order->fn_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[蜂鸟]订单"
                    ]);
                } else {
                    $this->ding_error("取消蜂鸟订单失败");
                }
            }
            if (in_array($order->ss_status, [20, 30])) {
                if ($order->shipper_type_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                } else {
                    $shansong = app("shansong");
                }
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 3, '美团外卖聚合');
                    $order->status = 99;
                    $order->ss_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[闪送]订单"
                    ]);
                } else {
                    $this->ding_error("取消闪送订单失败");
                }
            }
            if (in_array($order->mqd_status, [20, 30])) {
                $meiquanda = app("meiquanda");
                $result = $meiquanda->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    $order->status = 99;
                    $order->mqd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[美全达]订单"
                    ]);
                } else {
                    $this->ding_error("取消美全达订单失败");
                }
            }
            if (in_array($order->dd_status, [20, 30])) {
                if ($order->shipper_type_dd) {
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    $dada = app("dada");
                }
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 5, '美团外卖聚合');
                    $order->status = 99;
                    $order->dd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[达达]订单"
                    ]);
                } else {
                    $this->ding_error("取消达达订单失败");
                }
            }
            if (in_array($order->uu_status, [20, 30])) {
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 6, '美团外卖聚合');
                    $order->status = 99;
                    $order->uu_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[UU]订单"
                    ]);
                } else {
                    $this->ding_error("取消UU订单失败");
                }
            }
            if (in_array($order->sf_status, [20, 30])) {
                if ($order->shipper_type_sf) {
                    $sf = app("shunfengservice");
                } else {
                    $sf = app("shunfeng");
                }
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 7, '美团外卖聚合');
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
                    //         Log::info("饿了么取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    //         $this->ding_error("饿了么取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    //     }
                    // }
                    $order->status = 99;
                    $order->sf_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[顺丰]订单"
                    ]);
                } else {
                    $this->ding_error("取消顺丰订单失败");
                }
            }
            return json_encode(["data" => "ok"]);
        } else {
            // 状态小于20，属于未发单，直接操作取消
            if ($order->status < 0) {
                $order->status = -10;
            } else {
                $order->status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
            }
            $order->save();
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "[美团外卖]取消订单"
            ]);
            $this->log_info("未配送");
            return json_encode(["data" => "ok"]);
        }

        return false;
    }
}
