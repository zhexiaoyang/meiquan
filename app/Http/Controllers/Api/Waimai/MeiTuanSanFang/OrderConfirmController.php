<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanSanFang;

use App\Jobs\CreateMtOrder;
use App\Jobs\PrintWaiMaiOrder;
use App\Jobs\PushDeliveryOrder;
use App\Jobs\SendOrderToErp;
use App\Jobs\VipOrderSettlement;
use App\Models\ErpAccessShop;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use App\Models\VipProduct;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use App\Models\WmOrderReceive;
use App\Models\WmPrinter;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderConfirmController
{
    use NoticeTool, LogTool;

    public $prefix_title = '[美团餐饮确认订单回调&###]';

    public function confirm(Request $request)
    {
        if (!$data = $request->get('message')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        // 门店订单ID
        $shop_id = $request->get('ePoiId');
        $mt_order_id = $data['order_id'];
        $status = $data['status'];
        /********************* 美团心跳测试-返回成功 *********************/
        if (!$shop_id || !$mt_order_id) {
            return json_encode(['code' => '0', 'message' => 'success']);
        }
        \Log::info('餐饮来单全部参数', $request->all());
        $this->ding_exception('餐饮来单');
        // 全部参数
        $status_texts = [2 => '用户已支付', 4 => '商家已接单', 8 => '订单已完成', 9 => '订单已取消'];
        $status_text = $status_texts[$status] ?? '状态不存在';
        $this->prefix = str_replace('###', "&状态:{$status_text},门店:{$shop_id},订单号:{$mt_order_id}", $this->prefix_title);
        $this->log_info('-全部参数', $data);
        if ($status == 4) {
            return $this->confirm_order($mt_order_id, $shop_id, $data);
        } elseif ($status == 8) {
            return $this->complete_order($mt_order_id);
        } elseif ($status == 9) {
            return $this->cancel_order($mt_order_id);
        }

        return json_encode(['code' => '0', 'message' => 'success']);
    }

    public function confirm_order($mt_order_id, $wm_shop_id, $data)
    {
        $mt = app('mtkf');
        $res = $mt->wmoper_order_recipient_info($mt_order_id, $wm_shop_id);
        // 获取订单详情
        $data2 = $res['data'] ?? [];
        if (!$data2) {
            $this->log_info('未获取到订单详情');
            // return false;
        }
        /********************* 判断订单是否存在 *********************/
        if (Order::where("order_id", $mt_order_id)->exists()) {
            $this->log_info('订单已存在');
            return json_encode(['data' => 'OK']);
        }
        /********************* 查找门店 *********************/
        if (!$shop = Shop::where("waimai_mt", $wm_shop_id)->first()) {
            if (!$shop = Shop::where("mt_shop_id", $wm_shop_id)->first()) {
                $this->log_info('没有找到门店');
                return json_encode(['data' => 'ok']);
            }
        }
        $this->log_info("-门店信息,ID:{$shop->id},名称:{$shop->shop_name}");
        DB::transaction(function () use ($shop, $wm_shop_id, $mt_order_id, $data, $data2) {
            /******************** 操作逻辑 *************** 操作逻辑 **************** 操作逻辑 *****************/
            // 状态
            $status_filter = [1 => 1, 2 => 1, 4 => 4, 6 => 14, 8 => 18, 9 => 30];
            /********************* 创建外卖订单数组 *********************/
            // 取餐类型（0：普通取餐；1：到店取餐）
            $pick_type = $data['pick_type'] ?? 0;
            // 用户预计送达时间
            $delivery_time = $data['delivery_time'];
            // 商品信息
            $products = json_decode(urldecode($data['wmAppOrderFoods']), true);
            // 对账信息
            $poi_receive_detail = json_decode($data['poiReceiveDetail'], true);
            // 配送模式
            $logistics_code = isset($data['logistics_code']) ? intval($data['logistics_code']) : 0;
            if ($logistics_code > 0) {
                if ($logistics_code === 1001) {
                    $logistics_code = 1;
                }
                if ($logistics_code === 2002) {
                    $logistics_code = 2;
                }
                if ($logistics_code === 3001) {
                    $logistics_code = 3;
                }
            }
            // 创建外卖订单数组
            if ($mt_order_id[0] == 1) {
                $mt_shop_id = substr($mt_order_id, 0, 8);
            } else {
                $mt_shop_id = substr($mt_order_id, 0, 7);
            }
            $order_wm_data = [
                'user_id' => $shop->user_id,
                "shop_id" => $shop->id,
                "order_id" => $mt_order_id,
                "wm_order_id_view" => $data['wm_order_id_view'],
                // 订单平台（1 美团外卖，2 饿了么，3京东到家，4美全达）
                "platform" => 1,
                // 订单来源（1 民康，2 美全美团服务商，3 美全饿了么服务商，4 寝趣，5 美团开放平台餐饮）
                "from_type" => 5,
                "app_poi_code" => $mt_shop_id,
                "wm_shop_name" => $data['wm_poi_name'],
                "recipient_name" => $data2['recipientName'] ?? ($data['recipient_name'] ?? '无名客人'),
                "recipient_phone" => $data2['recipientPhone'] ?? ($data['recipient_phone'] ?? ''),
                "recipient_address" => $data2['recipientAddressDesensitization'] ?? '',
                // "recipient_address" => $data2['recipientAddress'],
                // "recipient_address_detail" => $data2['recipientAddress'],
                "latitude" => $data['latitude'],
                "longitude" => $data['longitude'],
                "shipping_fee" => $data['shippingFee'],
                "total" => $data['total'],
                "original_price" => $data['originalPrice'],
                // "package_bag_money_yuan" => $data['package_bag_money_yuan'] ?? 0,
                "service_fee" => $poi_receive_detail['foodShareFeeChargeByPoi'] / 100,
                "logistics_fee" => $poi_receive_detail['logisticsFee'] / 100,
                "online_payment" => $poi_receive_detail['onlinePayment'] / 100,
                "poi_receive" => $poi_receive_detail['wmPoiReceiveCent'] / 100,
                // "rebate_fee" => $poi_receive_detail['agreementCommissionRebateAmount'] ?? 0,
                "caution" => $data['caution'],
                // "shipper_phone" => $data2['shipperPhone'] ?? "",
                "status" => $status_filter[$data['status']] ?? 4,
                "ctime" => $data['ctime'],
                "estimate_arrival_time" => $data['estimate_arrival_time'] ?? 0,
                "utime" => $data['utime'],
                "delivery_time" => $data['delivery_time'],
                "pick_type" => $data['pick_type'] ?? 0,
                "day_seq" => $data['day_seq'] ?? 0,
                // "invoice_title" => $data['invoiceTitle'] ?? '',
                // "taxpayer_id" => $data['taxpayerId'] ?? '',
                // "is_favorites" => intval($data['isFavorites'] ?? 0),
                // "is_poi_first_order" => intval($data['isPoiFirstOrder'] ?? 0),
                "logistics_code" => $logistics_code,
                "is_vip" => $shop->vip_mt,
            ];
            $this->log_info('$order_wm_data', $order_wm_data);
            // 创建外卖订单
            $order_wm = WmOrder::create($order_wm_data);
            $this->log_info("-外卖订单创建成功，ID:{$order_wm->id}");
            // 商品信息
            $items = [];
            // VIP成本价
            $cost_money = 0;
            // 组合商品数组，计算成本价
            if (!empty($products)) {
                foreach ($products as $product) {
                    $upc = $product['upc'] ?? '';
                    $quantity = $product['quantity'] ?? 0;
                    $_tmp = [
                        'order_id' => $order_wm->id,
                        'app_food_code' => $product['appFoodCode'] ?? '',
                        'box_num' => $product['box_num'] ?? 0,
                        'box_price' => $product['box_price'] ?? 0,
                        'sku_id' => $product['skuId'] ?? '',
                        'food_property' => $product['food_property'] ?? '',
                        // 'food_discount' => $product['food_discount'] ?? 0,
                        // 'food_share_fee' => $product['foodShareFeeChargeByPoi'] ?? 0,
                        // 'cart_id' => $product['cart_id'] ?? 0,
                        'mt_tag_id' => $product['mtTagId'] ?? 0,
                        'mt_spu_id' => $product['mtSpuId'] ?? 0,
                        'mt_sku_id' => $product['mtSkuId'] ?? 0,
                        'food_name' => $product['food_name'] ?? '',
                        'unit' => $product['unit'] ?? '',
                        'upc' => $upc,
                        'quantity' => $product['quantity'] ?? 0,
                        'price' => $product['price'] ?? 0,
                        'spec' => $product['spec'] ?? '',
                        'vip_cost' => 0
                    ];
                    if ($upc) {
                        if ($shop->vip_status) {
                            $cost = VipProduct::select('cost')->where(['upc' => $upc, 'shop_id' => $shop->id])->first();
                            if (isset($cost->cost)) {
                                $cost = $cost->cost;
                                if ($cost > 0) {
                                    $cost_money += ($cost * $quantity);
                                    $_tmp['vip_cost'] = $cost;
                                    // $cost_data[] = ['upc' => $product['upc'], 'cost' => $cost->cost];
                                    $this->log_info("-VIP订单成本价,upc:{$upc},价格:{$cost}");
                                } else {
                                    $this->log_info("-VIP订单成本价小于等于0,upc:{$upc},价格:{$cost}");
                                }
                            } else {
                                $this->log_info("-成本价不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                            }
                        }
                    } else {
                        $this->log_info("UPC不存在");
                    }
                    $items[] = $_tmp;
                }
            }
            if (!empty($items)) {
                if ($shop->vip_status) {
                    $this->log_info("-成本价计算：{$cost_money}|shop_id：{$shop->id},order_id：{$order_wm->order_id}");
                    $order_wm->vip_cost = $cost_money;
                    $order_wm->save();
                    $this->log_info("-外卖订单,VIP商家成本价更新成功");
                }
                WmOrderItem::insert($items);
                $this->log_info("-外卖订单「商品」保存成功");
            }
            $this->log_info('$items', $items);
            $receives = [];
            if (!empty($poi_receive_detail['actOrderChargeByMt'])) {
                foreach ($poi_receive_detail['actOrderChargeByMt'] as $receive) {
                    if ($receive['moneyCent'] > 0) {
                        $receives[] = [
                            'type' => 1,
                            'order_id' => $order_wm->id,
                            'comment' => $receive['comment'],
                            'fee_desc' => $receive['feeTypeDesc'],
                            'money' => $receive['moneyCent'] / 100,
                        ];
                    }
                }
            }
            if (!empty($poi_receive_detail['actOrderChargeByPoi'])) {
                foreach ($poi_receive_detail['actOrderChargeByPoi'] as $receive) {
                    if ($receive['moneyCent'] > 0) {
                        $receives[] = [
                            'type' => 2,
                            'order_id' => $order_wm->id,
                            'comment' => $receive['comment'],
                            'fee_desc' => $receive['feeTypeDesc'],
                            'money' => $receive['moneyCent'],
                        ];
                    }
                }
            }
            if (!empty($receives)) {
                $this->log_info("-外卖订单「对账」保存成功");
                WmOrderReceive::insert($receives);
            }
            $this->log_info('$receives', $receives);
            /********************* 创建跑腿订单数组 *********************/
            // 创建订单数组
            $order_pt_data = [
                'delivery_id' => $mt_order_id,
                'user_id' => $shop->user_id,
                'order_id' => $mt_order_id,
                'shop_id' => $shop->id,
                'wm_poi_name' => $data['wm_poi_name'],
                'delivery_service_code' => "4011",
                "receiver_name" => $data2['recipientName'] ?? ($data['recipient_name'] ?? '无名客人'),
                "receiver_phone" => $data2['recipientPhone'] ?? ($data['recipient_phone'] ?? ''),
                "receiver_address" => $data2['recipientAddressDesensitization'] ?? '',
                "receiver_lng" => $data['longitude'],
                "receiver_lat" => $data['latitude'],
                "caution" => $data['caution'],
                'coordinate_type' => 0,
                "goods_value" => $data['total'],
                // 'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                'goods_weight' => 3,
                "day_seq" => $data['day_seq'],
                'platform' => 1,
                // 订单来源（3 洁爱眼，4 民康，5 寝趣，6 闪购，7 餐饮）
                'type' => 7,
                'status' => 0,
                'order_type' => $delivery_time ? 1 : 0
            ];
            // 判断是否预约单
            if ($delivery_time > 0) {
                $this->log_info("-跑腿订单,预约单,送达时间:" . strtotime("Y-m-d H:i:s", $delivery_time));
                // [预约单]待发送
                $order_pt_data['status'] = 3;
                $order_pt_data['order_type'] = 1;
                $order_pt_data['expected_pickup_time'] = $delivery_time - 3600;
                $order_pt_data['expected_delivery_time'] = $delivery_time;
            }
            // 判断是否自动发单
            // if (!$shop->mt_shop_id) {
            //     $order_pt_data['status'] = 7;
            // }
            // 创建跑腿订单
            $order_pt_data['wm_id'] = $order_wm->id;
            $order_pt = Order::create($order_pt_data);
            $this->log_info('$order_pt_data', $order_pt_data);
            $this->log_info("-跑腿订单创建成功，ID:{$order_pt->id}");
            OrderLog::create([
                "order_id" => $order_pt->id,
                "des" => "「美团外卖」创建订单"
            ]);
            // 获取发单设置
            $setting = OrderSetting::where('shop_id', $shop->id)->first();
            // 判断是否发单
            $this->log_info("-开始派单");
            if ($shop->mt_shop_id) {
                if ($pick_type == 0) {
                    if ($order_pt->order_type) {
                        $this->log_info("-预约单");
                        $qu = 2400;
                        if ($order_pt->distance <= 2) {
                            $qu = 1800;
                        }
                        dispatch(new PushDeliveryOrder($order_pt, ($order_pt->expected_delivery_time - time() - $qu)));
                        $this->log_info("-预约单派单成功，{$qu}秒后发单");
                    } else {
                        $order_pt->send_at = date("Y-m-d H:i:s");
                        $order_pt->status = 8;
                        $order_pt->save();
                        $delay = $setting->delay_send ?? 0;
                        $delay = $delay > 60 ? $delay : config("ps.order_delay_ttl");
                        dispatch(new CreateMtOrder($order_pt, $delay));
                        $this->log_info("-派单成功，{$delay}秒后发单");
                    }
                } else {
                    // 到店自取 ？？？ ， 更改状态，不在新订单列表里面显示
                    $this->log_info('-到店自取，不发单');
                }
            } else {
                $this->log_info('-未开通自动派单');
            }
            // 打印订单
            // if ($print = WmPrinter::where('shop_id', $shop->id)->first()) {
            //     $this->log_info('-打印订单，触发任务');
            //     dispatch(new PrintWaiMaiOrder($order_wm, $print));
            // }
            // 转仓库打印
            // if ($setting) {
            //     if ($setting->warehouse && $setting->warehouse_time && $setting->warehouse_print) {
            //         $this->log_info("-转单打印[setting：{$setting->id}", [$setting]);
            //         $time_data = explode('-', $setting->warehouse_time);
            //         $this->log_info("-转单打印-[time_data", [$time_data]);
            //         if (!empty($time_data) && (count($time_data) === 2)) {
            //             if (in_time_status($time_data[0], $time_data[1])) {
            //                 $this->log_info("-转单打印-[仓库ID：{$setting->warehouse}");
            //                 if ($print = WmPrinter::where('shop_id', $setting->warehouse)->first()) {
            //                     $this->log_info("-转单打印-[订单ID：{$order_wm->id}，订单号：{$order_wm->order_id}，门店ID：{$order_wm->shop_id}，仓库ID：{$setting->warehouse}]");
            //                     dispatch(new PrintWaiMaiOrder($order_wm, $print));
            //                 }
            //             }
            //         }
            //     }
            // }
            // 推送ERP
            if ($erp_shop = ErpAccessShop::where("mt_shop_id", $wm_shop_id)->first()) {
                if ($erp_shop->access_id === 4) {
                    $this->log_info("-推送ERP触发任务");
                    dispatch(new SendOrderToErp($data, $erp_shop->id));
                }
            }
            $this->ding_exception('餐饮创建订单成功');
        });
        $this->log_info("订单创建完成");
        return json_encode(['code' => '0', 'message' => 'success']);
    }

    public function complete_order($order_id)
    {
        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            if ($order->status < 18) {
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
                $this->log_info("订单号：{$order_id}|操作失败|系统订单状态：{$order->status}");
            }
        } else {
            $this->log_info("订单号：{$order_id}|订单不存在");
        }
        if ($order_pt = Order::where('order_id', $order_id)->first()) {
            if ($order_pt->status == 0) {
                $order_pt->status = 70;
                $order_pt->over_at = date("Y-m-d H:i:s");
                $order_pt->save();
                OrderLog::create([
                    "order_id" => $order_pt->id,
                    "des" => "「美团外卖」完成订单"
                ]);
            }
        }

        return json_encode(['code' => '0', 'message' => 'success']);
    }

    public function cancel_order($order_id)
    {
        if ($wmOrder = WmOrder::where('order_id', $order_id)->first()) {
            if ($wmOrder->status < 18) {
                $wmOrder->status = 30;
                $wmOrder->cancel_at = date("Y-m-d H:i:s");
                $wmOrder->save();
                $this->log_info("取消外卖订单-成功");
            } else {
                $this->log_info("外卖订单取消失败,外卖订单状态:{$wmOrder->status}");
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消美团订单返回失败",
                        "id" => $order->id,
                        "ps" => "美团",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消美团订单返回失败", $logs);
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "蜂鸟",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消蜂鸟订单返回失败", $logs);
                }
            } elseif ($ps == 3) {
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if (($result['status'] == 200) || ($result['msg'] = '订单已经取消')) {
                    $this->log_info("取消已接单闪送跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
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
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'ss_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消闪送订单返回失败",
                        "id" => $order->id,
                        "ps" => "闪送",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消闪送订单返回失败", $logs);
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消美全达订单返回失败",
                        "id" => $order->id,
                        "ps" => "美全达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消美全达订单返回失败", $logs);
                }
            } elseif ($ps == 5) {
                $dada = app("dada");
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    $this->log_info("取消已接单达达跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order) {
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
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'dd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消达达订单返回失败",
                        "id" => $order->id,
                        "ps" => "达达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消达达订单返回失败", $logs);
                }
            } elseif ($ps == 6) {
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    $this->log_info("取消已接单UU跑腿订单成功");
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消UU订单返回失败",
                        "id" => $order->id,
                        "ps" => "UU",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消UU订单返回失败", $logs);
                }
            } elseif ($ps == 7) {
                $sf = app("shunfeng");
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0) {
                    $this->log_info("取消已接单顺丰跑腿订单成功");
                    try {
                        DB::transaction(function () use ($order, $result) {
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
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'sf_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
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
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消顺丰订单返回失败",
                        "id" => $order->id,
                        "ps" => "顺丰",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消顺丰订单返回失败", $logs);
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
                }
            }
            if (in_array($order->ss_status, [20, 30])) {
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    $order->status = 99;
                    $order->ss_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[闪送]订单"
                    ]);
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
                }
            }
            if (in_array($order->dd_status, [20, 30])) {
                $dada = app("dada");
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->dd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[达达]订单"
                    ]);
                }
            }
            if (in_array($order->uu_status, [20, 30])) {
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    $order->status = 99;
                    $order->uu_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[UU]订单"
                    ]);
                }
            }
            if (in_array($order->sf_status, [20, 30])) {
                $sf = app("shunfeng");
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0) {
                    $order->status = 99;
                    $order->sf_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "[美团外卖]取消[顺丰]订单"
                    ]);
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
    }

    public function notice($message)
    {
        $this->log_info($message);
        $this->ding_exception($message);
    }
}
