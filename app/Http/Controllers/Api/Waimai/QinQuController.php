<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Http\Controllers\Controller;
use App\Jobs\CreateMtOrder;
use App\Jobs\PushDeliveryOrder;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QinQuController extends Controller
{
    public function create(Request $request)
    {
        \Log::info("[外卖-寝趣]-[推送已确认订单回调]-全部参数：", $request->all());

        if ($request->get('order_id')) {
            $mt_shop_id = $request->get("app_poi_code", "");
            $mt_order_id = $request->get("wm_order_id_view", "");

            // 创建跑腿订单
            if ($shop = Shop::query()->where("mt_shop_id", $mt_shop_id)->first()) {
                Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：正在创建跑腿订单");
                $mt_status = $request->get("status", 0);
                $pick_type = $request->get("pick_type", 0);
                $recipient_address = urldecode($request->get("recipient_address", ""));

                if ($pick_type === 1) {
                    Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：到店自取订单，不创建跑腿订单");
                    return json_encode(['data' => 'ok']);
                }

                if (strstr($recipient_address, "到店自取")) {
                    Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：到店自取订单，不创建跑腿订单");
                    return json_encode(['data' => 'ok']);
                }

                if (Order::where('order_id', $mt_order_id)->first()) {
                    Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：跑腿订单已存在");
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
                    Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：跑腿订单创建完毕");
                    if ($status === 0) {
                        if ($order->order_type) {
                            $qu = 2400;
                            if ($order->distance <= 2) {
                                $qu = 1800;
                            }

                            dispatch(new PushDeliveryOrder($order->id, ($order->expected_delivery_time - time() - $qu)));
                            Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：美团创建预约订单成功");
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
                            Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：派单单成功");
                            $order->send_at = date("Y-m-d H:i:s");
                            $order->status = 8;
                            $order->save();
                            dispatch(new CreateMtOrder($order, config("ps.order_delay_ttl")));
                        }
                    }
                }
            } else {
                Log::info("【外卖-寝趣-推送已确认订单】（{$mt_order_id}）：未开通自动接单");
                // Log::info('外卖-寝趣-推送已确认订单-未开通自动接单', ['shop_id' => $mt_shop_id, 'shop_name' => urldecode($request->get("wm_poi_name", ""))]);
            }
        }
        return json_encode(['data' => 'ok']);
    }
    public function cancel(Request $request)
    {
        \Log::info("[外卖-寝趣]-[推送用户或客服取消订单回调]-全部参数：", $request->all());
        return json_encode(['data' => 'ok']);
    }
}
