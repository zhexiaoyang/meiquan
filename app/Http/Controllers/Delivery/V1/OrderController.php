<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\MedicineDepot;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // 新订单( 10 new)、待抢单(20 pending)、待取货(30 receiving)、配送中(40 delivering)、配送异常(50 exceptional)、取消/退款(60 cancel)、催单(70 remind)
        $status = (int) $request->get('status', '');
        $page_size = $request->get('page_size', 10);
        if (!in_array($status, [10,20,30,40,50,60,70])) {
            $status = 10;
        }
        $query = Order::with(['products' => function ($query) {
            $query->select('order_id', 'food_name', 'spec', 'upc', 'quantity');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type', 'money', 'updated_at');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status');
        }])->select('id','order_id','wm_id','shop_id','wm_poi_name','receiver_name','receiver_phone','receiver_address','receiver_lng','receiver_lat',
            'caution','day_seq','platform','status','created_at', 'ps as logistic_type','push_at','receive_at','take_at','over_at','cancel_at')
            ->where('ignore', 0)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day')));
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }
        if ($status === 10) {
            $query->whereIn('status', [0, 3, 7, 8]);
        } elseif ($status === 20) {
            $query->where('status', 20);
        } elseif ($status === 30) {
            $query->where('status', 50);
        } elseif ($status === 40) {
            $query->where('status', 60);
        } elseif ($status === 50) {
            // 5 余额不足，10 暂无运力
            $query->whereIn('status', [10, 5]);
        } elseif ($status === 60) {
            // $query->where('status', 20);
            $query->with(['order' => function ($query) {
                $query->where('status', 30);
            }]);
        } elseif ($status === 70) {
            $query->whereIn('status', [0, 3, 7, 8])->where('remind_num', '>', 0);
        }
        $orders = $query->orderByDesc('id')->paginate($page_size);
        // 商品图片
        $images = [];
        if (!empty($orders)) {
            $upcs = [];
            foreach ($orders as $order) {
                if (!empty($order->products)) {
                    foreach ($order->products as $product) {
                        if ($product->upc) {
                            $upcs[] = $product->upc;
                        }
                    }
                }
            }
            if (!empty($upcs)) {
                $images = MedicineDepot::whereIn('upc', $upcs)->pluck('cover', 'upc');
            }
        }
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order->title = $this->setOrderListTitle($status, $order);
                // 商品信息
                if (!empty($order->products)) {
                    foreach ($order->products as $product) {
                        if ($product->upc) {
                            $product->image = $images[$product->upc] ?? '';
                        }
                    }
                    $order->poi_receive = $order->order->poi_receive ?? 0;
                    unset($order->order);
                }
            }
        }
        return $this->page($orders);
    }

    public function setOrderListTitle($status, $order)
    {
        if ($status === 10) {
            return '<font>' . tranTime(strtotime($order->created_at)) . '</font>下单';
        } elseif ($status === 20 && $order->push_at) {
            return '<font>' . tranTime(strtotime($order->push_at)) . '</font>发单';
        } elseif ($status === 30 && $order->receive_at) {
            return '<font>' . tranTime(strtotime($order->receive_at)) . '</font>接单';
        } elseif ($status === 40 && isset($order->order->estimate_arrival_time)) {
            // \Log::info($order->order->estimate_arrival_time);
            return '<font>' . tranTime2($order->order->estimate_arrival_time) . '</font>送达' . tranTime3($order->order->estimate_arrival_time);
        }
        return '';
    }

    public function show(Request $request)
    {
        if (!$order = Order::find(intval($request->get('id', 0)))) {
            return $this->error('订单不存在');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id'))) {
                return $this->error('订单不存在!');
            }
        }
        $order->load(['products' => function ($query) {
            $query->select('order_id', 'food_name', 'spec', 'upc', 'quantity');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type', 'money', 'updated_at');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status');
        }]);
        return $this->success($order);
    }

    public function cancel(Request $request)
    {
        return $this->success();
    }
}
