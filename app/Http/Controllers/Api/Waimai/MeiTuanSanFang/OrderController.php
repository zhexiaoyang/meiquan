<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanSanFang;

use App\Http\Controllers\Controller;
use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use App\Models\WmOrderReceive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public $prefix_title = '[美团外卖三方服务商-订单回调-###|订单号:$$$]';

    public function create(Request $request)
    {
        $this->prefix_title = str_replace('###', '创建订单', $this->prefix_title);
        if (!$data = $request->get('order')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);

        // 创建订单
        if (!$data['ePoiId']) {
            $this->log("外卖门店不存在,门店ID:{$data['ePoiId']}");
        }
        $shop = Shop::where('waimai_mt', $data['ePoiId'])->first();
        if (!$shop) {
            $shop = Shop::query()->where('mtwm', $data['ePoiId'])->first();
        }
        if (!$shop) {
            $this->log("门店不存在,门店ID:{$data['ePoiId']}");
            return json_encode(['data' => 'OK']);
        }

        $pick_type = $data['pickType'] ?? 0;
        $delivery_time = $data['deliveryTime'];
        $status_filter = [1 => 1, 2 => 1, 4 => 4, 6 => 14, 8 => 18, 9 => 30];
        $products = json_decode(urldecode($data['detail']), true);
        $poi_receive_detail = json_decode($data['poiReceiveDetail'], true);
        $logistics_code = isset($data['logisticsCode']) ? intval($data['logisticsCode']) : 0;
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

        $order_data = [
            "shop_id" => $shop->id,
            "order_id" => $order_id,
            "wm_order_id_view" => $data['orderIdView'],
            // 订单平台（1 美团外卖，2 饿了么，3京东到家，4美全达）
            "platform" => 1,
            // 订单来源（1 民康，2 美全美团服务商，3 美全饿了么服务商，4 寝趣，5 美团开放平台餐饮）
            "from_type" => 5,
            "app_poi_code" => $data['ePoiId'],
            "wm_shop_name" => $data['poiName'],
            "recipient_name" => $data['recipientName'] ?? "无名客人",
            "recipient_phone" => $data['recipientPhone'],
            "recipient_address" => $data['recipientAddressDesensitization'],
            "recipient_address_detail" => $data['recipientAddressDesensitization'],
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
            "shipper_phone" => $data['shipperPhone'] ?? "",
            "status" => $status_filter[$data['status']] ?? 4,
            "ctime" => $data['ctime'],
            "estimate_arrival_time" => $data['estimateArrivalTime'] ?? 0,
            "utime" => $data['utime'],
            "delivery_time" => $data['deliveryTime'],
            "pick_type" => $data['pickType'] ?? 0,
            "day_seq" => $data['daySeq'] ?? 0,
            "invoice_title" => $data['invoiceTitle'] ?? '',
            "taxpayer_id" => $data['taxpayerId'] ?? '',
            // "is_prescription" =>  $is_prescription,
            "is_favorites" => intval($data['isFavorites'] ?? 0),
            "is_poi_first_order" => intval($data['isPoiFirstOrder'] ?? 0),
            "logistics_code" => $logistics_code,
        ];


        $order = DB::transaction(function () use ($order_data, $products, $poi_receive_detail) {
            // 保存订单
            $order = WmOrder::query()->create($order_data);

            // 订单商品
            $order_items = [];
            foreach ($products as $product) {
                $order_items[] = [
                    'order_id' => $order->id,
                    'app_food_code' => $product['app_food_code'] ?? '',
                    'box_num' => $product['box_num'] ?? 0,
                    'box_price' => $product['box_price'] ?? 0,
                    'sku_id' => $product['sku_id'] ?? '',
                    'food_property' => $product['food_property'] ?? '',
                    'food_discount' => $product['food_discount'] ?? 0,
                    'food_share_fee' => $product['foodShareFeeChargeByPoi'] ?? 0,
                    'cart_id' => $product['cart_id'] ?? 0,
                    'mt_tag_id' => $product['mt_tag_id'] ?? 0,
                    'mt_spu_id' => $product['mt_spu_id'] ?? 0,
                    'mt_sku_id' => $product['mt_sku_id'] ?? 0,
                    'food_name' => $product['food_name'] ?? '',
                    'unit' => $product['unit'] ?? '',
                    'upc' => $product['upc'] ?? '',
                    'quantity' => $product['quantity'] ?? 0,
                    'price' => $product['price'] ?? 0,
                    'spec' => $product['spec'] ?? '',
                    'vip_cost' => 0
                ];
            }
            WmOrderItem::query()->insert($order_items);
            // 订单对账
            $receives = [];
            if (!empty($poi_receive_detail['actOrderChargeByMt'])) {
                foreach ($poi_receive_detail['actOrderChargeByMt'] as $receive) {
                    if ($receive['moneyCent'] > 0) {
                        $receives[] = [
                            'type' => 1,
                            'order_id' => $order->id,
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
                            'order_id' => $order->id,
                            'comment' => $receive['comment'],
                            'fee_desc' => $receive['feeTypeDesc'],
                            'money' => $receive['moneyCent'],
                        ];
                    }
                }
            }
            if (!empty($receives)) {
                WmOrderReceive::query()->insert($receives);
            }
            return $order;
        });

        // 创建跑腿订单
        if ($pick_type == 0) {
            $order_pt = [
                'delivery_id' => $order_id,
                'user_id' => $shop->user_id,
                'order_id' => $order_id,
                'shop_id' => $shop->id,
                'delivery_service_code' => "4011",
                'receiver_name' => $data['recipientName'] ?? "无名客人",
                'receiver_address' => $data['recipientAddressDesensitization'],
                'receiver_phone' => $data['recipientPhone'],
                'receiver_lng' => $data['longitude'],
                'receiver_lat' => $data['latitude'],
                'coordinate_type' => 0,
                'goods_value' => $data['total'],
                'goods_weight' => 3,
                'day_seq' => $data['daySeq'] ?? 0,
                'platform' => 1,
                // type=8，美团开放-餐饮
                'type' => 8,
                'status' => 0,
                'order_type' => 0
            ];
            // 判断是否预约单
            if ($delivery_time > 0) {
                $order_pt['order_type'] = 1;
                $order_pt['expected_pickup_time'] = $delivery_time - 3600;
                $order_pt['expected_delivery_time'] = $delivery_time;
            }
            $order_pt = new Order($order_pt);
            // 保存订单
            if ($order_pt->save()) {
                OrderLog::create([
                    "order_id" => $order_pt->id,
                    "des" => "（美团外卖）自动创建跑腿订单：{$order_pt->order_id}"
                ]);
            }
        }

        // 操作接单
        if ($order) {
            $mt = app('mtkf');
            $res = $mt->order_confirm($order->order_id, $order->app_poi_code);
            $this->log('接单返回', $res);
        }

        return json_encode(['data' => 'OK']);
    }

    public function confirm(Request $request)
    {
        $this->prefix_title = str_replace('###', '已确认', $this->prefix_title);
        if (!$data = $request->get('order')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);

        // 更改订单状态
        $status_filter = [1 => 1, 2 => 1, 4 => 4, 6 => 14, 8 => 18, 9 => 30];
        if ($order = WmOrder::where('order_id', $data['orderId'])->first()) {
            $order->status = $status_filter[$data['status']];
            $order->save();
        } else {
            // 创建订单
            if (!$data['ePoiId']) {
                $this->log("外卖门店不存在,门店ID:{$data['ePoiId']}");
            }
            $shop = Shop::where('waimai_mt', $data['ePoiId'])->first();
            if (!$shop) {
                $shop = Shop::query()->where('mtwm', $data['ePoiId'])->first();
            }
            if (!$shop) {
                $this->log("门店不存在,门店ID:{$data['ePoiId']}");
                return json_encode(['data' => 'OK']);
            }

            $pick_type = $data['pickType'] ?? 0;
            $delivery_time = $data['deliveryTime'];
            $status_filter = [1 => 1, 2 => 1, 4 => 4, 6 => 14, 8 => 18, 9 => 30];
            $products = json_decode(urldecode($data['detail']), true);
            $poi_receive_detail = json_decode($data['poiReceiveDetail'], true);
            $logistics_code = isset($data['logisticsCode']) ? intval($data['logisticsCode']) : 0;
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

            $order_data = [
                "shop_id" => $shop->id,
                "order_id" => $order_id,
                "wm_order_id_view" => $data['orderIdView'],
                // 订单平台（1 美团外卖，2 饿了么，3京东到家，4美全达）
                "platform" => 1,
                // 订单来源（1 民康，2 美全美团服务商，3 美全饿了么服务商，4 寝趣，5 美团开放平台餐饮）
                "from_type" => 5,
                "app_poi_code" => $data['ePoiId'],
                "wm_shop_name" => $data['poiName'],
                "recipient_name" => $data['recipientName'] ?? "无名客人",
                "recipient_phone" => $data['recipientPhone'],
                "recipient_address" => $data['recipientAddressDesensitization'],
                "recipient_address_detail" => $data['recipientAddressDesensitization'],
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
                "shipper_phone" => $data['shipperPhone'] ?? "",
                "status" => $status_filter[$data['status']] ?? 4,
                "ctime" => $data['ctime'],
                "estimate_arrival_time" => $data['estimateArrivalTime'] ?? 0,
                "utime" => $data['utime'],
                "delivery_time" => $data['deliveryTime'],
                "pick_type" => $data['pickType'] ?? 0,
                "day_seq" => $data['daySeq'] ?? 0,
                "invoice_title" => $data['invoiceTitle'] ?? '',
                "taxpayer_id" => $data['taxpayerId'] ?? '',
                // "is_prescription" =>  $is_prescription,
                "is_favorites" => intval($data['isFavorites'] ?? 0),
                "is_poi_first_order" => intval($data['isPoiFirstOrder'] ?? 0),
                "logistics_code" => $logistics_code,
            ];

            $order = DB::transaction(function () use ($order_data, $products, $poi_receive_detail) {
                // 保存订单
                $order = WmOrder::query()->create($order_data);

                // 订单商品
                $order_items = [];
                foreach ($products as $product) {
                    $order_items[] = [
                        'order_id' => $order->id,
                        'app_food_code' => $product['app_food_code'] ?? '',
                        'box_num' => $product['box_num'] ?? 0,
                        'box_price' => $product['box_price'] ?? 0,
                        'sku_id' => $product['sku_id'] ?? '',
                        'food_property' => $product['food_property'] ?? '',
                        'food_discount' => $product['food_discount'] ?? 0,
                        'food_share_fee' => $product['foodShareFeeChargeByPoi'] ?? 0,
                        'cart_id' => $product['cart_id'] ?? 0,
                        'mt_tag_id' => $product['mt_tag_id'] ?? 0,
                        'mt_spu_id' => $product['mt_spu_id'] ?? 0,
                        'mt_sku_id' => $product['mt_sku_id'] ?? 0,
                        'food_name' => $product['food_name'] ?? '',
                        'unit' => $product['unit'] ?? '',
                        'upc' => $product['upc'] ?? '',
                        'quantity' => $product['quantity'] ?? 0,
                        'price' => $product['price'] ?? 0,
                        'spec' => $product['spec'] ?? '',
                        'vip_cost' => 0
                    ];
                }
                WmOrderItem::query()->insert($order_items);
                // 订单对账
                $receives = [];
                if (!empty($poi_receive_detail['actOrderChargeByMt'])) {
                    foreach ($poi_receive_detail['actOrderChargeByMt'] as $receive) {
                        if ($receive['moneyCent'] > 0) {
                            $receives[] = [
                                'type' => 1,
                                'order_id' => $order->id,
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
                                'order_id' => $order->id,
                                'comment' => $receive['comment'],
                                'fee_desc' => $receive['feeTypeDesc'],
                                'money' => $receive['moneyCent'],
                            ];
                        }
                    }
                }
                if (!empty($receives)) {
                    WmOrderReceive::query()->insert($receives);
                }
                return $order;
            });

            // 创建跑腿订单
            if ($pick_type == 0) {
                $order_pt = [
                    'delivery_id' => $order_id,
                    'user_id' => $shop->user_id,
                    'order_id' => $order_id,
                    'shop_id' => $shop->id,
                    'delivery_service_code' => "4011",
                    'receiver_name' => $data['recipientName'] ?? "无名客人",
                    'receiver_address' => $data['recipientAddressDesensitization'],
                    'receiver_phone' => $data['recipientPhone'],
                    'receiver_lng' => $data['longitude'],
                    'receiver_lat' => $data['latitude'],
                    'coordinate_type' => 0,
                    'goods_value' => $data['total'],
                    'goods_weight' => 3,
                    'day_seq' => $data['daySeq'] ?? 0,
                    'platform' => 1,
                    // type=8，美团开放-餐饮
                    'type' => 8,
                    'status' => 0,
                    'order_type' => 0
                ];
                // 判断是否预约单
                if ($delivery_time > 0) {
                    $order_pt['order_type'] = 1;
                    $order_pt['expected_pickup_time'] = $delivery_time - 3600;
                    $order_pt['expected_delivery_time'] = $delivery_time;
                }
                $order_pt = new Order($order_pt);
                // 保存订单
                if ($order_pt->save()) {
                    OrderLog::create([
                        "order_id" => $order_pt->id,
                        "des" => "（美团外卖）自动创建跑腿订单：{$order_pt->order_id}"
                    ]);
                }
            }
            $dingding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
            $dingding->sendTextMsg("餐饮服务商确认订单-不存在，订单号:{$data['orderId']}");
        }

        return json_encode(['data' => 'OK']);
    }

    public function cancel(Request $request)
    {
        $this->prefix_title = str_replace('###', '已确认', $this->prefix_title);
        if (!$data = $request->get('orderCancel')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);
        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            if ($order->status < 18) {
                WmOrder::where('id', $order->id)->update([
                    'status' => 30,
                    'cancel_reason' => $data['reason'] ?? '',
                    'cancel_at' => date("Y-m-d H:i:s")
                ]);
            } else {
                $this->log("订单状态不正确，不能取消。订单状态：{$order->status}");
            }
            if ($order_pt = Order::where('order_id', $order_id)->first()) {
                // 取消跑腿订单
                $dingding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
                $dingding->sendTextMsg("餐饮服务商取消跑腿订单，订单号:{$order_id}");
            }
        }

        return json_encode(['data' => 'OK']);
    }

    public function refund(Request $request)
    {
        $this->prefix_title = str_replace('###', '退款', $this->prefix_title);
        if (!$data = $request->get('orderRefund')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);
        if ($data['notifyType'] == 'agree') {
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                WmOrder::where('id', $order->id)->update([
                    'refund_status' => 1,
                    'refund_fee' => $order->total,
                    'refund_at' => date("Y-m-d H:i:s")
                ]);
            }
        }

        return json_encode(['data' => 'OK']);
    }

    public function rider(Request $request)
    {
        $this->prefix_title = str_replace('###', '配送状态', $this->prefix_title);
        if (!$data = $request->get('shippingStatus')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);
        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            $status = $data['shippingStatus'] ?? '';
            $name = $data['dispatcherName'] ?? '';
            $phone = $data['dispatcherMobile'] ?? '';
            $time = $data['time'] ?? '';
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
            }
        }

        return json_encode(['data' => 'OK']);
    }

    public function finish(Request $request)
    {
        $this->prefix_title = str_replace('###', '完成', $this->prefix_title);
        if (!$data = $request->get('order')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);
        if ($order = WmOrder::where('order_id', $order_id)->first()) {
            if ($order->status < 18) {
                WmOrder::where('id', $order->id)->update([
                    'status' => 18,
                    'finish_at' => date("Y-m-d H:i:s")
                ]);
            } else {
                $this->log("订单状态不正确，不能完成。订单状态：{$order->status}");
            }
        }

        return json_encode(['data' => 'OK']);
    }

    public function partrefund(Request $request)
    {
        $this->prefix_title = str_replace('###', '部分退款', $this->prefix_title);
        if (!$data = $request->get('partOrderRefund')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);
        if ($data['notifyType'] == 'agree') {
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                WmOrder::where('id', $order->id)->update([
                    'refund_status' => 2,
                    'refund_fee' => $data['money'],
                    'refund_at' => date("Y-m-d H:i:s")
                ]);
            }
        }

        return json_encode(['data' => 'OK']);
    }

    public function remind(Request $request)
    {
        $this->prefix_title = str_replace('###', '催单', $this->prefix_title);
        if (!$data = $request->get('pushOrderRemind')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);

        return json_encode(['data' => 'OK']);
    }

    public function down(Request $request)
    {
        // $this->prefix_title = str_replace('###', '降级', $this->prefix_title);
        // $this->log('全部参数', $request->all());

        return json_encode(['data' => 'OK']);
    }

    public function bill(Request $request)
    {
        $this->prefix_title = str_replace('###', '账单', $this->prefix_title);
        if (!$data = $request->get('tradeDetail')) {
            return json_encode(['data' => 'OK']);
        }
        $data = json_decode($data, true);
        $order_id = $data['orderId'];
        $this->prefix = str_replace('$$$', $order_id, $this->prefix_title);
        $this->log('全部参数', $data);

        return json_encode(['data' => 'OK']);
    }
}
