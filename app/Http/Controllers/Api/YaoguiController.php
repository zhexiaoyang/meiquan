<?php


namespace App\Http\Controllers\Api;


use App\Jobs\CreateMtOrder;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YaoguiController
{
    public function settlement(Request $request)
    {
        Log::info('药柜-结算订单', $request->all());

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function downgrade(Request $request)
    {
        Log::info('药柜-隐私号降级', $request->all());

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function create(Request $request)
    {
        Log::info('药柜-创建订单', $request->all());

        $data = $request->get("params");

        if (!empty($data) && count($data) > 20) {
            // 创建订单信息
            $order_data = [
                'delivery_id' => $data['orderNo'],
                'order_id' => $data['orderNo'],
                'shop_id' => $data['appStoreCode'],
                'delivery_service_code' => "4011",
                'receiver_name' => $data['deliveryAddress']['receiverName'],
                'receiver_address' => $data['deliveryAddress']['receiverAddress'],
                'receiver_phone' => $data['deliveryAddress']['receiverPhone'],
                'receiver_lng' => $data['deliveryAddress']['receiverLongitude'],
                'receiver_lat' => $data['deliveryAddress']['receiverLatitude'],
                'coordinate_type' => 0,
                'goods_value' => $data['totalAmount'],
                'goods_weight' => 4.5,
                'type' => 11,
                'status' => 0,
                'goods_pickup_info' => substr($data['fourthPartyOrderId'], -6)
            ];

            $order = new Order($order_data);

            if ($order->save()) {
                // dispatch(new CreateMtOrder($order));
                \Log::info('众柜创建订单成功', $order->toArray());
            }
        }

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function cancel(Request $request)
    {

        $res = ["code" => 200, "message" => "SUCCESS"];

        Log::info('药柜-取消订单', $request->all());

        $data = $request->get("params");

        $order_id = $data['orderNo'] ?? '';

        if ($order_id) {

            $order = Order::query()->where('order_id', $order_id)->first();

            if (!$order) {
                \Log::info('药柜接口取消订单-订单未找到', ['请求参数' => $request->all()]);
            }

            \Log::info('药柜接口取消订单-信息', ['请求参数' => $request->all(), '订单信息' => $order->toArray()]);

            $ps = $order->ps;
            $shop = Shop::query()->find($order->shop_id);

            if ($ps == 1) {
                $meituan = app("meituan");

                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->peisong_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);

                if ($result['code'] === 0 && ($order->status < 99)) {
                    if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('美团取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('美团取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                } else {
                    \Log::info('美团取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    return json_encode($res);
                }
            } elseif ($ps == 2) {

                $fengniao = app("fengniao");

                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);

                if ($result['code'] == 200 && ($order->status < 99)) {
                    if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                } else {
                    \Log::info('蜂鸟取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    return json_encode($res);
                }
            } elseif ($ps == 3 && ($order->status < 99)) {

                $shansong = app("shansong");

                $result = $shansong->cancelOrder($order->peisong_id);

                if ($result['status'] == 200 && ($order->status < 99)) {
                    if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                } else {
                    \Log::info('闪送取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    return json_encode($res);
                }
            } else {
                \Log::info('药柜取消订单-未配送');
                return json_encode($res);
            }

            \Log::info('药柜取消订单-失败');
        }
    }

    public function urge(Request $request)
    {
        Log::info('药柜-催单', $request->all());

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }
}