<?php

namespace App\Http\Controllers\Api;

use App\Jobs\CreateMtOrder;
use App\Jobs\PushDeliveryOrder;
use App\Jobs\SendOrderToErp;
use App\Models\ErpAccessShop;
use App\Models\MkOrder;
use App\Models\MkOrderItem;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MinKangController
{
    public function confirm(Request $request)
    {
        if ($request->get('order_id')) {
            $mt_shop_id = $request->get("app_poi_code", "");
            $mt_order_id = $request->get("wm_order_id_view", "");
            Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：开始");

            if (!$mt_shop_id || !$mt_order_id) {
                return json_encode(['data' => 'ok']);
            }

            if (Order::query()->where("order_id", $mt_order_id)->first()) {
                Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：民康平台异常-订单已存在");
                return json_encode(['data' => 'ok']);
            }
            // $order_data = [
            //     "order_id" => $mt_order_id,
            //     // "order_tag_list" => urldecode($request->get("order_tag_list", "")),
            //     "wm_order_id_view" => $request->get("wm_order_id_view", ""),
            //     "app_poi_code" => $mt_shop_id,
            //     "wm_poi_name" => urldecode($request->get("wm_poi_name", "")),
            //     "wm_poi_address" => urldecode($request->get("wm_poi_address", "")),
            //     "wm_poi_phone" => $request->get("wm_poi_phone", ""),
            //     "recipient_address" => urldecode($request->get("recipient_address", "")),
            //     "recipient_phone" => $request->get("recipient_phone", ""),
            //     // "backup_recipient_phone" => urldecode($request->get("backup_recipient_phone", "")),
            //     "recipient_name" => urldecode($request->get("recipient_name", "")) ?? "无名客人",
            //     "shipping_fee" => $request->get("shipping_fee", 0),
            //     "total" => $request->get("total", 0),
            //     "original_price" => $request->get("original_price", 0),
            //     "caution" => urldecode($request->get("caution", "")),
            //     "shipper_phone" => $request->get("shipper_phone", "") ?? "",
            //     "status" => $request->get("status", 0),
            //     "ctime" => $request->get("ctime", 0),
            //     "utime" => $request->get("utime", 0),
            //     "delivery_time" => $request->get("delivery_time", 0),
            //     "is_third_shipping" => $request->get("is_third_shipping", 0) ?? 0,
            //     "pick_type" => $request->get("pick_type", 0) ?? 0,
            //     "latitude" => $request->get("latitude", 0),
            //     "longitude" => $request->get("longitude", 0),
            //     "invoice_title" => $request->get("invoice_title", "") ?? "",
            //     "day_seq" => $request->get("day_seq", 0) ?? 0,
            //     "logistics_code" => $request->get("logistics_code", "") ?? "",
            //     "package_bag_money" => $request->get("package_bag_money", 0) ?? 0,
            //     "package_bag_money_yuan" => $request->get("package_bag_money_yuan", "") ?? 0,
            //     "total_weight" => $request->get("total_weight", 0) ?? 0,
            //     "mt_created_at" => date("Y-m-d H:i:s", $request->get("ctime", 0)),
            //     "mt_updated_at" => date("Y-m-d H:i:s", $request->get("utime", 0)),
            // ];
            //
            // // 商家对账
            // $poi_receive_detail_yuan = $request->get("poi_receive_detail_yuan", "") ?? "";
            // if ($poi_receive_detail_yuan) {
            //     $order_data["poi_receive_detail_yuan"] = urldecode($poi_receive_detail_yuan);
            // }
            // // 订单优惠信息
            // // $extras = $request->get("extras");
            // // if ($extras) {
            // //     $order_data["extras"] = urldecode($extras);
            // // }
            // // 商品优惠信息
            // // $sku_benefit_detail = $request->get("sku_benefit_detail");
            // // if ($sku_benefit_detail) {
            // //     $order_data["sku_benefit_detail"] = urldecode($sku_benefit_detail);
            // // }
            // // 订单商品信息
            // $detail = $request->get("detail");
            //
            // // 创建订单
            // $order = new MkOrder($order_data);
            // if ($order->save() && $detail) {
            //     Log::info("【民康-推送已确认订单】（{$mt_order_id}）：订单信息创建完毕");
            //     $products = json_decode(urldecode($detail), true);
            //     if (!empty($products)) {
            //         // Log::info('民康-已确认订单-商品列表', $products);
            //         $goods_price = 0;
            //         $items = [];
            //         foreach ($products as $product) {
            //             $goods_price += $product['price'] * 100 * $product['quantity'];
            //             $tmp['order_id'] = $order->id;
            //             $tmp['mt_order_id'] = $mt_order_id;
            //             $tmp['app_food_code'] = $product['app_food_code'];
            //             $tmp['food_name'] = $product['food_name'];
            //             $tmp['sku_id'] = $product['sku_id'];
            //             $tmp['upc'] = $product['upc'];
            //             $tmp['quantity'] = $product['quantity'];
            //             $tmp['price'] = $product['price'];
            //             $tmp['box_num'] = $product['box_num'];
            //             $tmp['box_price'] = $product['box_price'];
            //             $tmp['unit'] = $product['unit'];
            //             $tmp['spec'] = $product['spec'] ?? "";
            //             $tmp['weight'] = $product['weight'];
            //             $items[] = $tmp;
            //         }
            //     }
            //     $order->goods_price = $goods_price / 100;
            //     $order->save();
            //     MkOrderItem::query()->insert($items);
            //     Log::info("【民康-推送已确认订单】（{$mt_order_id}）：商品信息创建完毕");
            // }

            // 创建跑腿订单
            if ($shop = Shop::query()->where("mt_shop_id", $mt_shop_id)->first()) {
                Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：正在创建跑腿订单");
                $mt_status = $request->get("status", 0);
                $pick_type = $request->get("pick_type", 0);
                $recipient_address = urldecode($request->get("recipient_address", ""));

                if ($pick_type === 1) {
                    Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：到店自取订单，不创建跑腿订单");
                    return json_encode(['data' => 'ok']);
                }

                if (strstr($recipient_address, "到店自取")) {
                    Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：到店自取订单，不创建跑腿订单");
                    return json_encode(['data' => 'ok']);
                }

                if (Order::where('order_id', $mt_order_id)->first()) {
                    Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：跑腿订单已存在");
                    return json_encode(['data' => 'ok']);
                }

                $status = 0;
                if ($mt_status < 4) {
                    $status = -30;
                }
                if ($mt_status > 4) {
                    $status = -10;
                }

                $weight = $request->get("total_weight", 0) ?? 0;
                $delivery_time = $request->get("delivery_time", 0);
                // 创建订单数组
                $order_pt = [
                    'delivery_id' => $mt_order_id,
                    'user_id' => $shop->user_id,
                    'order_id' => $mt_order_id,
                    'shop_id' => $shop->id,
                    'delivery_service_code' => "4011",
                    'receiver_name' => urldecode($request->get("recipient_name", "")) ?? "无名客人",
                    'receiver_address' => $recipient_address,
                    'receiver_phone' => $request->get("recipient_phone", ""),
                    'receiver_lng' => $request->get("longitude", 0),
                    'receiver_lat' => $request->get("latitude", 0),
                    'coordinate_type' => 0,
                    'goods_value' => $request->get("total", 0),
                    // 'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                    'goods_weight' => 3,
                    'day_seq' => $request->get("day_seq", 0),
                    'platform' => 1,
                    'type' => 4,
                    'status' => $status,
                    'order_type' => 0
                ];

                // 判断是否预约单
                if ($delivery_time > 0) {
                    if ($status === 0) {
                        $order_pt['status'] = 3;
                    }
                    $order_pt['order_type'] = 1;
                    $order_pt['expected_pickup_time'] = $delivery_time - 3600;
                    $order_pt['expected_delivery_time'] = $delivery_time;
                }

                $order = new Order($order_pt);
                // 保存订单
                if ($order->save()) {
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "（美团外卖）自动创建跑腿订单：{$order->order_id}"
                    ]);
                    Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：跑腿订单创建完毕");
                    if ($status === 0) {
                        if ($order->order_type) {
                            $qu = 2400;
                            if ($order->distance <= 2) {
                                $qu = 1800;
                            }

                            dispatch(new PushDeliveryOrder($order, ($order->expected_delivery_time - time() - $qu)));
                            Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：美团创建预约订单成功");
                            // \Log::info('美团创建预约订单成功', ['id' => $order->id, 'order_id' => $order->order_id]);

                            // $ding_notice = app("ding");
                            // $logs = [
                            //     "des" => "接到预订单：" . $qu,
                            //     "datetime" => date("Y-m-d H:i:s"),
                            //     "order_id" => $order->order_id,
                            //     "status" => $order->status,
                            //     "ps" => $order->ps
                            // ];
                            // $ding_notice->sendMarkdownMsgArray("接到美团预订单", $logs);
                        } else {
                            Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：派单单成功");
                            $order->send_at = date("Y-m-d H:i:s");
                            $order->status = 8;
                            $order->save();
                            dispatch(new CreateMtOrder($order, config("ps.order_delay_ttl")));
                        }
                    }
                }
            } else {
                Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：未开通自动接单");
                return json_encode(['data' => 'ok']);
                // Log::info('民康-推送已确认订单-未开通自动接单', ['shop_id' => $mt_shop_id, 'shop_name' => urldecode($request->get("wm_poi_name", ""))]);
            }

            // 推送ERP
            if ($erp_shop = ErpAccessShop::query()->where("mt_shop_id", $mt_shop_id)->first()) {
                if ($erp_shop->access_id === 4) {
                    Log::info("【民康平台-推送已确认订单】（{$mt_order_id}）：推送ERP开始");
                    dispatch(new SendOrderToErp($erp_shop->id, $order));
                }
            }
            // return json_encode(['data' => 'ok']);
        }
        // return 200;
    }
}
