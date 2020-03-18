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

    /**
     * 订单列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');
        $status = $request->get('status');
        $query = Order::with(['shop' => function($query) {
            $query->select('shop_id', 'shop_name');
        }])->select('id','shop_id','order_id','mt_peisong_id','receiver_name','receiver_phone','money','status','created_at');

        // 关键字搜索
        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('delivery_id', 'like', "%{$search_key}%")
                    ->orWhere('order_id', 'like', "%{$search_key}%")
                    ->orWhere('mt_peisong_id', 'like', "%{$search_key}%")
                    ->orWhere('receiver_name', 'like', "%{$search_key}%")
                    ->orWhere('receiver_phone', 'like', "%{$search_key}%");
            });
        }

        // 判断可以查询的药店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('shop_id'));
        }

        // 状态查询
        if (!is_null($status)) {
            $query->where('status', $status);
        }

        // 查询订单
        $orders = $query->where('status', '>', -3)->orderBy('id', 'desc')->paginate($page_size);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (in_array($order->status, [0, 20 ,30])) {
                    $order->is_cancel = 1;
                } else {
                    $order->is_cancel = 0;
                }
                $order->status_code = $order->status;
                $order->status = $order->status_label;
                if (isset($order->shop->shop_name)) {
                    $order->shop_name = $order->shop->shop_name;
                } else {
                    $order->shop_name = "";
                }
                unset($order->shop);
            }
        }
        return $this->success($orders);
    }

    /**
     * 创建订单
     * @param Request $request
     * @param Order $order
     * @return mixed
     */
    public function store(Request $request, Order $order)
    {
        $shop_id = $request->get('shop_id', 0);
        if (!$shop = Shop::query()->find($shop_id)) {
            $shop = Shop::query()->where('shop_id', $shop_id)->first();
        }
        if (!$shop) {
            return $this->error('门店不存在');
        }
        $order->fill($request->all());
        $order->shop_id = $shop->shop_id;
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
            'cancel_reason_id' => 399,
            'cancel_reason' => '其他原因',
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

        if (!$type || !in_array($type, [1,2,3]) || !$order_id) {
            return $this->error('参数错误');
        }

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif($type === 2) {
            $meituan = app("mrx");
        } else {
            $meituan = app("jay");
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

            // 设置状态
            $status = -1;
            if ($data['status'] < 4) {
                $status = -2;
            }
            if ($data['status'] > 4) {
                $status = -3;
            }

            // 设置重量
            $weight = isset($data['total_weight']) ? $data['total_weight'] : 0;

            // 创建订单信息
            $order_data = [
                'delivery_id' => $data['wm_order_id_view'],
                'order_id' => $data['wm_order_id_view'],
                'shop_id' => $shop_id,
                'delivery_service_code' => "4011",
                'receiver_name' => $data['recipient_name'],
                'receiver_address' => $data['recipient_address'],
                'receiver_phone' => $data['recipient_phone'],
                'receiver_lng' => $data['longitude'],
                'receiver_lat' => $data['latitude'],
                'coordinate_type' => 0,
                'goods_value' => $data['total'],
                'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                'type' => $type,
                'status' => $status,
            ];

            // 判断是否预约单
            if (isset($data['delivery_time']) && $data['delivery_time'] > 0) {
                $order_data['order_type'] = 1;
                $order_data['expected_pickup_time'] = $data['delivery_time'] - 3600;
                $order_data['expected_delivery_time'] = $data['delivery_time'];
            }

            // 创建订单
            $order = new Order($order_data);

            // 保存订单
            if ($order->save()) {
                if ($status === -1) {
                    dispatch(new CreateMtOrder($order));
                }
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
                'cancel_reason_id' => 399,
                'cancel_reason' => '其他原因',
            ]);

            if ($result['code'] === 0 && $order->update(['status' => 99])) {
                return $this->success([]);
            }
        }

        return $this->error("取消失败");
    }

    public function money(Request $request, Shop $shop)
    {
        $lng = $request->get('lng', 0);
        $lat = $request->get('lat', 0);
        $weight = $request->get('weight', 0);

        if (!$lng || !$lat || !$weight) {
            return $this->error('参数错误');
        }

        $distance = distanceMoney($shop, $lng, $lat);

        if ($distance == -2) {
            return $this->error('获取距离错误请稍后再试');
        }

        if ($distance == -1) {
            return $this->error('超出配送距离');
        }

        $base = baseMoney($shop->city_level ?: 9);
        $time = timeMoney();
        $date_money = dateMoney();
        $weight = weightMoney($weight);

        return $this->success([
            'base' => $base,
            'time' => $time,
            'date_money' => $date_money,
            'weight' => $weight,
            'distance' => $distance,
            'total' => $base + $time + $date_money + $distance + $weight
        ]);
    }
}
