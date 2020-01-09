<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtOrder;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except('sync', 'cancel');
    }

    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');
        $query = Order::query();
        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('delivery_id', 'like', "%{$search_key}%")
                    ->orWhere('order_id', 'like', "%{$search_key}%")
                    ->orWhere('mt_peisong_id', 'like', "%{$search_key}%")
                    ->orWhere('receiver_name', 'like', "%{$search_key}%")
                    ->orWhere('receiver_phone', 'like', "%{$search_key}%");
            });
        }
        $orders = $query->orderBy('id', 'desc')->paginate($page_size);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order->status = $order->status_label;
            }
        }
        return $this->success($orders);
    }

    public function store(Request $request, Order $order)
    {
        $order->fill($request->all());
        if ($order->save()) {
            dispatch(new CreateMtOrder($order));
            return $this->success([]);
        }
        return $this->error("创建失败");
    }

    public function destroy(Order $order)
    {
        $meituan = app("meituan");
        $result = $meituan->delete([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id,
            'cancel_reason_id' => 101,
            'cancel_reason' => '顾客主动取消',
        ]);

        if ($result['code'] === 0 && $order->update(['status' => 99])) {
            return $this->success([]);
        }

        return $this->error("取消失败");
    }

    public function show(Order $order)
    {
        $order->status = $order->status_label;
        return $this->success($order);
    }

    public function checkStatus(Order $order)
    {

        $meituan = app("meituan");
        $result = $meituan->queryStatus([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id,
        ]);

        return $this->success($result);
    }

    public function location(Order $order)
    {

        $meituan = app("meituan");
        $result = $meituan->location([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id,
        ]);

        return $this->success($result);
    }

    public function sync(Request $request)
    {
        $type = intval($request->get('type', 0));
        $order_id = $request->get('order_id', 0);

        if (!$type || !in_array($type, [1,2]) || !$order_id) {
            return $this->error('参数错误');
        }

        if ($type === 1) {
            $meituan = app("yaojite");
        } else {
            $meituan = app("meiquan");
        }

        $res = $meituan->getOrderDetail(['order_id' => $order_id]);
        if (!empty($res) && is_array($res['data']) && !empty($res['data'])) {
            $data = $res['data'];
            if (Order::where('order_id', $data['wm_order_id_view'])->first()) {
                return $this->error('订单已存在');
            }

            $shop_id = isset($data['app_poi_code']) ? $data['app_poi_code'] : 0;

            if (!$shop = Shop::where('shop_id', $shop_id)->first()) {
                return $this->error('药店不存在');
            }

            $weight = 0;

            if (isset($data['detail']) && is_string($data['detail'])) {
                $goods_data = json_decode($data['detail'], true);
                if (!empty($goods_data)) {
                    foreach ($goods_data as $goods) {
                        $weight += $goods['weight'];
                    }
                }
            }

            $order = new Order([
                // 'delivery_id' => $data['delivery_id'],
                'order_id' => $data['wm_order_id_view'],
                'shop_id' => $shop_id,
                'delivery_service_code' => "4012",
                'receiver_name' => $data['recipient_name'],
                'receiver_address' => $data['recipient_address'],
                'receiver_phone' => $data['recipient_phone'],
                'receiver_lng' => $data['longitude'],
                'receiver_lat' => $data['latitude'],
                'coordinate_type' => 0,
                'goods_value' => $data['total'],
                'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
            ]);

            if ($order->save()) {
                // if (!$order->delivery_id) {
                //     $order->delivery_id = $order->order_id;
                //     $order->save();
                // }
                dispatch(new CreateMtOrder($order));
            }
            return $this->success([]);
        }
        return $this->error('未获取到订单');
    }

    public function cancel(Request $request)
    {
        $order = Order::where('order_id', $request->get('order_id', 0))->first();
        if ($order) {
            $meituan = app("meituan");
            $result = $meituan->delete([
                'delivery_id' => $order->delivery_id,
                'mt_peisong_id' => $order->mt_peisong_id,
                'cancel_reason_id' => 101,
                'cancel_reason' => '顾客主动取消',
            ]);

            if ($result['code'] === 0 && $order->update(['status' => 99])) {
                return $this->success([]);
            }
        }

        return $this->error("取消失败");
    }
}
