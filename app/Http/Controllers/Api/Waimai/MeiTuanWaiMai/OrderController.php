<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Jobs\PrescriptionFeeDeductionJob;
use App\Jobs\VipOrderSettlement;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\VipBillItem;
use App\Models\VipProduct;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use App\Task\TakeoutOrderVoiceNoticeTask;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController
{
    use LogTool, NoticeTool;

    public $prefix_title = '[美团外卖回调&###]';
    // 部分扣款，退款商品成本价
    public $dec_cost = 0;

    public function create(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&支付订单|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
            $app_poi_code = $request->get('app_poi_code', '');
            // $data = ['15243198'];
            // if (in_array($app_poi_code, $data)) {
            //     $meituan = app("meiquan");
            //     $res = $meituan->orderConfirm($order_id, $app_poi_code);
            //     $this->log_info("订单号：{$order_id}|操作接单返回信息", $res);
            // }
            if ($shop = Shop::select('id', 'mt_jie')->where('waimai_mt', $app_poi_code)->first()) {
                if ($shop->mt_jie === 1) {
                    $meituan = app("meiquan");
                    $res = $meituan->orderConfirm($order_id, $app_poi_code);
                    $this->log_info("闪购门店{$app_poi_code}订单号{$order_id}|操作接单返回信息", $res);
                }
            }
        }

        return json_encode(['data' => 'ok']);
    }

    public function refund(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $notify_type = $request->get('notify_type');
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&全部退款|订单号:{$order_id},类型:{$notify_type}", $this->prefix_title);
            if ($notify_type == 'agree') {
                if ($order = WmOrder::where('order_id', $order_id)->first()) {
                    WmOrder::where('id', $order->id)->update([
                        'refund_status' => 1,
                        'refund_fee' => $order->total,
                        'refund_at' => date("Y-m-d H:i:s")
                    ]);
                    if ($shop = Shop::find($order->shop_id)) {
                        Task::deliver(new TakeoutOrderVoiceNoticeTask(7, $shop->account_id ?: $shop->user_id), true);
                    }
                    // Task::deliver(new TakeoutOrderVoiceNoticeTask(7, $order->user_id), true);
                }
                $this->log_info('全部参数', $request->all());
            }
        }

        return json_encode(['data' => 'ok']);
    }

    public function partrefund(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $shop = null;
            $money = $request->get('money');
            $notify_type = $request->get('notify_type');
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&部分退款|订单号:{$order_id},类型:{$notify_type},金额:{$money}", $this->prefix_title);
            if (($notify_type == 'agree') && ($money > 0)) {
                if ($order = WmOrder::where('order_id', $order_id)->first()) {
                    // if ($order->status != 18) {
                    //     $this->ding_error("订单未完成，部分退款");
                    // }
                    $this->log_info('全部参数', $request->all());
                    WmOrder::where('id', $order->id)->update([
                        'refund_status' => 2,
                        'refund_fee' => $money,
                        'refund_at' => date("Y-m-d H:i:s"),
                    ]);
                    $food_str = $request->get('food');
                    $foods = json_decode($food_str, true);
                    if (!empty($foods)) {
                        $shop = Shop::find($order->shop_id);
                        DB::transaction(function () use ($order, $foods, $shop) {
                            $vip = $order->is_vip;
                            $dec_cost = 0;
                            $where['order_id'] = $order->id;
                            foreach ($foods as $food) {
                                $where['upc'] = $food['upc'];
                                $count = $food['count'];
                                if ($item = WmOrderItem::where($where)->where('quantity', '>', 0)->first()) {
                                    WmOrderItem::where('id', $item->id)->update([
                                        'quantity' => $item->quantity - $count,
                                        'refund_quantity' => $count,
                                    ]);
                                }
                                if ($vip && (strtotime($shop->vip_at) < strtotime('2022-11-25'))) {
                                    $cost = VipProduct::select('cost')->where(['upc' => $food['upc'], 'shop_id' => $order->shop_id])->first();
                                    if (isset($cost->cost)) {
                                        $dec_cost += ($cost->cost ?? 0);
                                    } else {
                                        $this->log_info("成本价不存在，订单ID:{$order->order_id}");
                                    }
                                } else {
                                    $cost = Medicine::select('guidance_price')->where(['upc' => $food['upc'], 'shop_id' => $order->shop_id])->first();
                                    if (isset($cost->guidance_price)) {
                                        $dec_cost += ($cost->guidance_price ?? 0);
                                    } else {
                                        $this->log_info("成本价不存在，订单ID:{$order->order_id}");
                                    }
                                }
                            }
                            $this->dec_cost = $dec_cost;
                            $vip_cost = $order->vip_cost - $dec_cost;
                            WmOrder::where('id', $order->id)->update(['vip_cost' => $vip_cost > 0 ? $vip_cost : 0]);
                        });
                        if ($order->is_vip) {
                            // 如果是VIP订单，触发结算JOB
                            // dispatch(new VipOrderSettlement($order));
                            // 退款记录
                            if ($platform == 4) {
                                $minkang = app('minkang');
                                $res = $minkang->getOrderRefundDetail($order_id);
                            } elseif ($platform == 31) {
                                $minkang = app('meiquan');
                                $res = $minkang->getOrderRefundDetail($order_id, false, $order->app_poi_code);
                            }

                            if (!empty($res['data']) && is_array($res['data'])) {
                                // VIP门店各方利润百分比
                                $commission = $shop->vip_commission;
                                $commission_manager = $shop->vip_commission_manager;
                                $commission_operate = $shop->vip_commission_operate;
                                $commission_internal = $shop->vip_commission_internal;
                                $business = 100 - $commission - $commission_manager - $commission_operate - $commission_internal;
                                foreach ($res['data'] as $v) {
                                    $poi_receive = $v['refund_partial_estimate_charge']['settle_amount'];
                                    if ($poi_receive) {
                                        $total = $poi_receive + $this->dec_cost;
                                        $vip_city = sprintf("%.2f",$total * $commission_manager / 100);
                                        $vip_operate = sprintf("%.2f", $total * $commission_operate / 100);
                                        $vip_internal = sprintf("%.2f",$total * $commission_internal / 100);
                                        $vip_business = sprintf("%.2f",$total * $business / 100);
                                        $vip_company = sprintf("%.2f",$total - $vip_operate - $vip_city - $vip_internal - $vip_business);
                                        $item = [
                                            'order_id' => $order->id,
                                            'shop_id' => $order->shop_id,
                                            'order_no' => $order->order_id,
                                            'platform' => $order->platform,
                                            'app_poi_code' => $order->app_poi_code,
                                            'wm_shop_name' => $order->wm_shop_name,
                                            'day_seq' => $order->day_seq,
                                            'trade_type' => 3,
                                            'status' => $order->status,
                                            'order_at' => $order->created_at,
                                            'finish_at' => $order->finish_at,
                                            'bill_date' => date("Y-m-d"),
                                            'vip_settlement' => $poi_receive,
                                            'vip_cost' => $this->dec_cost,
                                            'vip_permission' => 0,
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
                                        \Log::info("VIP订单结算处理，部分退款订单结算成功");
                                    } else {
                                        $this->ding_error('部分退款未获取到退款结算金额');
                                    }
                                }
                            }
                        }
                    }
                    if ($shop) {
                        Task::deliver(new TakeoutOrderVoiceNoticeTask(7, $shop->account_id ?: $shop->user_id), true);
                    }
                }
            }
        }
        return json_encode(['data' => 'ok']);
    }

    public function rider(Request $request, $platform)
    {
        $order_id = $request->get('order_id', '');
        $status = $request->get('logistics_status', '');
        $time = $request->get('time', 0);
        $name = $request->get('dispatcher_name', '');
        $phone = $request->get('dispatcher_mobile', '');

        if ($order_id && $status) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&美配订单状态回调|配送状态:{$status}|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if (in_array($status, [10, 20, 40]) && $order->status < 16) {
                    if ($status == 10) {
                        $order->status = 12;
                        $order->receive_at = date("Y-m-d H:i:s", $time ?: time());
                    } elseif ($status == 20) {
                        $order->status = 14;
                        $order->send_at = date("Y-m-d H:i:s", $time ?: time());
                    } elseif ($status == 40) {
                        $order->status = 16;
                        $order->deliver_at = date("Y-m-d H:i:s", $time ?: time());
                    }
                    if ($name) {
                        $order->shipper_name = $name;
                        $order->shipper_phone = $phone;
                    }
                    $order->save();
                    $this->log_info("订单号：{$order_id}|操作完成");
                } else {
                    $this->log_info("订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log_info("订单号：{$order_id}|订单不存在");
            }
        }

        return json_encode(['data' => 'ok']);
    }

    public function rider_exception(Request $request)
    {
        \Log::info('美配异常订单', $request->all());
        return json_encode(['data' => 'ok']);
    }

    public function status_self(Request $request, $platform)
    {
        $order_id = $request->get('order_view_id', '');
        $status = $request->get('logistics_status', '');
        $time = $request->get('operate_time', 0);

        if ($order_id && $status) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&自配订单状态|配送状态:{$status}|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 20 && $order->status < 14) {
                    $order->send_at = date("Y-m-d H:i:s", $time ?: time());
                    $order->status = 14;
                    $order->save();
                    $this->log_info("订单号：{$order_id}|操作完成");
                } else {
                    $this->log_info("订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log_info("订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }

    /**
     * 美团完成订单-统一回调
     * @data 2022/4/12 4:08 下午
     */
    public function finish(Request $request, $platform)
    {
        // 订单号
        $order_id = $request->get('wm_order_id_view', '');
        // 订单状态
        $status = $request->get('status', '');

        if ($order_id && $status) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&完成订单|订单状态:{$status}|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 8 && $order->status < 18) {
                    $bill_date = date("Y-m-d");
                    if (($order->ctime < strtotime($bill_date)) && (time() < strtotime(date("Y-m-d 09:00:00")))) {
                        $bill_date = date("Y-m-d", time() - 86400);
                    }
                    $order->bill_date = $bill_date;
                    $order->status = 18;
                    $order->finish_at = date("Y-m-d H:i:s");
                    $order->save();
                    $this->log_info("订单号：{$order_id}|操作完成");
                    if ($order->is_vip) {
                        // 如果是VIP订单，触发JOB
                        dispatch(new VipOrderSettlement($order));
                    }
                } else {
                    $this->log_info("订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
                // 处方标识
                $order_tag_list = json_decode(urldecode($request->get('order_tag_list')), true);
                if (in_array(8, $order_tag_list)) {
                    // 对账明细
                    $poi_receive_detail_yuan = json_decode(urldecode($request->get('poi_receive_detail_yuan')), true);
                    // \Log::info("完成订单扣款处方|{$order_id}}", $poi_receive_detail_yuan);
                    $reconciliationExtras = json_decode($poi_receive_detail_yuan['reconciliationExtras'] ?? '', true);
                    // \Log::info("完成订单扣款处方|{$order_id}}", $reconciliationExtras);
                    $platformChargeFee2 = (float) $reconciliationExtras['platformChargeFee2'] ?? null;
                    // \Log::info("完成订单扣款处方|{$order_id}|{$platformChargeFee2}");
                    PrescriptionFeeDeductionJob::dispatch($order->id, $platformChargeFee2);
                }
            } else {
                $this->log_info("订单号：{$order_id}|订单不存在");
            }
            if ($order_pt = Order::where('order_id', $order_id)->first()) {
                if ($order_pt->status == 0) {
                    $order_pt->status = 75;
                    $order_pt->over_at = date("Y-m-d H:i:s");
                    $order_pt->save();
                    OrderLog::create([
                        "order_id" => $order_pt->id,
                        "des" => "「美团外卖」完成订单"
                    ]);
                }
            }
        }
        return json_encode(['data' => 'ok']);
    }

    public function settlement(Request $request, $platform)
    {
        $order_id = $request->get('order_id', '');
        $status = $request->get('status', '');
        $fee = $request->get('settleAmount', '');
        if ($order_id && $status) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&订单结算|订单状态:{$status}|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                $this->log_info('VIP全部参数', $request->all());
                if (floatval($order->poi_receive) != floatval($fee)) {
                    $this->ding_error("结算金额不一致异常,status:{$status},order_id:{$order_id},poi_receive:{$order->poi_receive},fee:{$fee},时间:".date("Y-m-d H:i:s"));
                }
            } else {
                $this->log_info('全部参数', $request->all());
            }
        }
        return json_encode(['data' => 'ok']);
    }

    public function remind(Request $request, $platform)
    {
        $voice = 5;
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&催单", $this->prefix_title);

        if ($order_id = $request->get("order_id", "")) {
            $this->log_info('全部参数', $request->all());
            if ($order = WmOrder::select('user_id')->where('order_id', $order_id)->first()) {
                if ($shop = Shop::find($order->shop_id)) {
                    Task::deliver(new TakeoutOrderVoiceNoticeTask($voice, $shop->account_id ?: $shop->user_id), true);
                }
            }
            // Task::deliver(new TakeoutOrderVoiceNoticeTask($voice, 1), true);
        }

        return json_encode(['data' => 'ok']);
    }

    public function down(Request $request, $platform)
    {
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&隐私号降级", $this->prefix_title);

        $data = $request->all();

        if (!empty($data)) {
            $this->log_info('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }
}
