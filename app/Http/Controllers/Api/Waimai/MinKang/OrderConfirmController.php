<?php

namespace App\Http\Controllers\Api\Waimai\MinKang;

use App\Jobs\CreateMtOrder;
use App\Jobs\PrintWaiMaiOrder;
use App\Jobs\PushDeliveryOrder;
use App\Jobs\SendOrderToErp;
use App\Models\ErpAccessShop;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
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

    public $prefix_title = '美团外卖&民康&已确认订单[$$$]';

    public function confirm(Request $request)
    {
        if ($request->get('order_id')) {
            // 门店订单ID
            $mt_shop_id = $request->get("app_poi_code", "");
            $mt_order_id = $request->get("wm_order_id_view", "");
            // 全部参数
            $data = $request->all();
            $this->prefix = str_replace('$$$', "门店:{$mt_shop_id},订单号:{$mt_order_id}", $this->prefix_title);
            $this->log_info('-开始');
            $this->log_info('-全部参数', $data);
            /********************* 美团心跳测试-返回成功 *********************/
            if (!$mt_shop_id || !$mt_order_id) {
                return json_encode(['data' => 'ok']);
            }
            /********************* 判断订单是否存在 *********************/
            if (Order::where("order_id", $mt_order_id)->first()) {
                $this->log_info('订单已存在');
                return json_encode(['data' => 'ok']);
            }
            /********************* 查找门店 *********************/
            if (!$shop = Shop::where("waimai_mt", $mt_shop_id)->first()) {
                if (!$shop = Shop::where("mt_shop_id", $mt_shop_id)->first()) {
                    $this->log_info('没有找到门店');
                    return json_encode(['data' => 'ok']);
                }
            }
            $this->log_info("-门店信息,ID:{$shop->id},名称:{$shop->shop_name}");
            DB::transaction(function () use ($shop, $mt_shop_id, $mt_order_id, $data) {
                $products = json_decode(urldecode($data['detail']), true);
                $poi_receive_detail_yuan = json_decode(urldecode($data['poi_receive_detail_yuan']), true);
                /******************** 操作逻辑 *************** 操作逻辑 **************** 操作逻辑 *****************/
                /********************* 创建外卖订单数组 *********************/
                // 是否处方
                $order_tag_list = json_decode(urldecode($data['order_tag_list']), true);
                $is_prescription = 0;
                $prescription_fee = 0;
                if (in_array(8, $order_tag_list)) {
                    $is_prescription = 1;
                    $prescription_fee = 1.5;
                }
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
                $order_wm_data = [
                    "shop_id" => $shop->id ?? 0,
                    "order_id" => $mt_order_id,
                    "wm_order_id_view" => $mt_order_id,
                    // 订单平台（1 美团外卖，2 饿了么，3京东到家，4美全达）
                    "platform" => 1,
                    // 订单来源（1 民康，2 美全美团服务商，3 美全饿了么服务商，4 寝趣，5 美团开放平台餐饮）
                    "from_type" => 1,
                    "app_poi_code" => $mt_shop_id,
                    "wm_shop_name" => urldecode($data['wm_poi_name'] ?? ''),
                    "recipient_name" => urldecode($data['recipient_name']) ?? "无名客人",
                    "recipient_phone" => $data['recipient_phone'],
                    "recipient_address" => urldecode($data['recipient_address']),
                    "recipient_address_detail" => urldecode($data['recipient_address_detail']  ?? ''),
                    "latitude" => $data['latitude'],
                    "longitude" => $data['longitude'],
                    "shipping_fee" => $data['shipping_fee'],
                    "total" => $data['total'],
                    "original_price" => $data['original_price'],
                    "package_bag_money_yuan" => $data['package_bag_money_yuan'] ?? 0,
                    "service_fee" => $poi_receive_detail_yuan['foodShareFeeChargeByPoi'] ?? 0,
                    "logistics_fee" => $poi_receive_detail_yuan['logisticsFee'] ?? 0,
                    "online_payment" => $poi_receive_detail_yuan['onlinePayment'] ?? 0,
                    "poi_receive" => $poi_receive_detail_yuan['poiReceive'] ?? 0,
                    "rebate_fee" => $poi_receive_detail_yuan['agreementCommissionRebateAmount'] ?? 0,
                    "caution" => urldecode($data['caution']),
                    "shipper_phone" => $data['shipper_phone'] ?? "",
                    "status" => $status_filter[$data['status']] ?? 4,
                    "ctime" => $data['ctime'],
                    "estimate_arrival_time" => $data['estimate_arrival_time'] ?? 0,
                    "utime" => $data['utime'],
                    "delivery_time" => $data['delivery_time'],
                    "pick_type" => $data['pick_type'] ?? 0,
                    "day_seq" => $data['day_seq'] ?? 0,
                    "invoice_title" => urldecode($data['invoice_title'] ?? ''),
                    "taxpayer_id" => $data['taxpayer_id'] ?? '',
                    "is_prescription" =>  $is_prescription,
                    "is_favorites" => intval($data['is_favorites'] ?? 0),
                    "is_poi_first_order" => intval($data['is_poi_first_order'] ?? 0),
                    "logistics_code" => $logistics_code,
                    "is_vip" => $shop->vip_status,
                    "prescription_fee" => $prescription_fee,
                ];
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
                        $quantity = $product['quantity'] ?? 0;
                        $_tmp = [
                            'order_id' => $order_wm->id,
                            'app_food_code' => $product['app_food_code'] ?? '',
                            'food_name' => $product['food_name'] ?? '',
                            'unit' => $product['unit'] ?? '',
                            'upc' => $product['upc'] ?? '',
                            'quantity' => $quantity,
                            'price' => $product['price'] ?? 0,
                            'spec' => $product['spec'] ?? '',
                            'vip_cost' => 0
                        ];
                        if ($shop->vip_status) {
                            $upc = $product['upc'];
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
                        $items[] = $_tmp;
                    }
                }
                if (!empty($items)) {
                    if ($shop->vip_status) {
                        $this->log_info("-成本价计算：{$cost_money}|shop_id：{$shop->id},order_id：{$order_wm->order_id}");
                        $order_wm->vip_cost = $cost_money;
                        // $order->vip_cost_info = json_encode($cost_data, JSON_UNESCAPED_UNICODE);
                        $order_wm->save();
                        $this->log_info("-外卖订单,VIP商家成本价更新成功");
                    }
                    WmOrderItem::insert($items);
                    $this->log_info("-外卖订单「商品」保存成功");
                }
                $receives = [];
                if (!empty($poi_receive_detail_yuan['actOrderChargeByMt'])) {
                    foreach ($poi_receive_detail_yuan['actOrderChargeByMt'] as $receive) {
                        if ($receive['money'] > 0) {
                            $receives[] = [
                                'type' => 1,
                                'order_id' => $order_wm->id,
                                'comment' => $receive['comment'],
                                'fee_desc' => $receive['feeTypeDesc'],
                                'money' => $receive['money'],
                            ];
                        }
                    }
                }
                if (!empty($poi_receive_detail_yuan['actOrderChargeByPoi'])) {
                    foreach ($poi_receive_detail_yuan['actOrderChargeByPoi'] as $receive) {
                        if ($receive['money'] > 0) {
                            $receives[] = [
                                'type' => 2,
                                'order_id' => $order_wm->id,
                                'comment' => $receive['comment'],
                                'fee_desc' => $receive['feeTypeDesc'],
                                'money' => $receive['money'],
                            ];
                        }
                    }
                }
                if (!empty($receives)) {
                    $this->log_info("-外卖订单「对账」保存成功");
                    WmOrderReceive::insert($receives);
                }
                /********************* 创建跑腿订单数组 *********************/
                $pick_type = $data['pick_type'] ?? 0;
                $weight = $data['total_weight'] ?? 0;
                $delivery_time = $data['delivery_time'];
                // 创建订单数组
                $order_pt_data = [
                    'delivery_id' => $mt_order_id,
                    'user_id' => $shop->user_id,
                    'order_id' => $mt_order_id,
                    'shop_id' => $shop->id,
                    'wm_poi_name' => urldecode($data['wm_poi_name'] ?? ''),
                    'delivery_service_code' => "4011",
                    'receiver_name' => urldecode($data['recipient_name'] ?? '') ?: "无名客人",
                    "receiver_address" => urldecode($data['recipient_address']),
                    'receiver_phone' => $data['recipient_phone'] ?? '',
                    "receiver_lng" => $data['longitude'],
                    "receiver_lat" => $data['latitude'],
                    'coordinate_type' => 0,
                    "goods_value" => $data['total'],
                    // 'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                    'goods_weight' => 3,
                    "day_seq" => $data['day_seq'],
                    'platform' => 1,
                    'type' => 4,
                    'status' => 0,
                    'order_type' => 0
                ];
                // 判断是否预约单
                if ($delivery_time > 0) {
                    $this->log_info("-跑腿订单,预约单,送达时间:" . date("Y-m-d H:i:s", $delivery_time));
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
                            dispatch(new PushDeliveryOrder($order_pt->id, ($order_pt->expected_delivery_time - time() - $qu)));
                            $this->log_info("-预约单派单成功，{$qu}秒后发单");
                        } else {
                            $delay = $setting->delay_send ?? 0;
                            $delay = $delay > 60 ? $delay : config("ps.order_delay_ttl");
                            $order_pt->send_at = date("Y-m-d H:i:s", time() + $delay);
                            $order_pt->status = 8;
                            $order_pt->save();
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
                if ($print = WmPrinter::where('shop_id', $shop->id)->first()) {
                    $this->log_info('-打印订单，触发任务');
                    dispatch(new PrintWaiMaiOrder($order_wm->id, $print));
                }
                // 转仓库打印
                if ($setting) {
                    if ($setting->warehouse && $setting->warehouse_time && $setting->warehouse_print) {
                        $this->log_info("-转单打印[setting：{$setting->id}", [$setting]);
                        $time_data = explode('-', $setting->warehouse_time);
                        $this->log_info("-转单打印-[time_data", [$time_data]);
                        if (!empty($time_data) && (count($time_data) === 2)) {
                            if (in_time_status($time_data[0], $time_data[1])) {
                                $this->log_info("-转单打印-[仓库ID：{$setting->warehouse}");
                                if ($print = WmPrinter::where('shop_id', $setting->warehouse)->first()) {
                                    $this->log_info("-转单打印-[订单ID：{$order_wm->id}，订单号：{$order_wm->order_id}，门店ID：{$order_wm->shop_id}，仓库ID：{$setting->warehouse}]");
                                    dispatch(new PrintWaiMaiOrder($order_wm->id, $print));
                                }
                            }
                        }
                    }
                }
                // 推送ERP
                if ($erp_shop = ErpAccessShop::where("mt_shop_id", $mt_shop_id)->first()) {
                    if ($erp_shop->access_id === 4) {
                        $this->log_info("-推送ERP触发任务");
                        dispatch(new SendOrderToErp($data, $erp_shop->id));
                    }
                }
            });
        }
        $this->log_info("-结束");
        return json_encode(['data' => 'ok']);
    }

    public function notice($message)
    {
        $this->log_info($message);
        $this->ding_exception($message);
    }
}
