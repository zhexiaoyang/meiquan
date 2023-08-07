<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\MedicineDepot;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function statistics(Request $request)
    {
        $order_where = [['ignore', '=', 0], ['created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day'))],];
        $wm_order_where = [['created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day'))],];
        // $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
        // $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
        // // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
            $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
        }
        $result = [
            'new' => Order::select('id')->where($order_where)->whereIn('status', [0, 3, 7, 8])->count(),
            'pending' => Order::select('id')->where($order_where)->where('status', 20)->count(),
            'receiving' => Order::select('id')->where($order_where)->where('status', 50)->count(),
            'delivering' => Order::select('id')->where($order_where)->where('status', 60)->count(),
            'exceptional' => Order::select('id')->where($order_where)->whereIn('status', [10, 5])->count(),
            'refund' => WmOrder::select('id')->where($wm_order_where)->where('status', 30)->count(),
            'remind' => Order::select('id')->where($order_where)->where('status', '>', 70)->where('remind_num', '>', 0)->count(),
        ];
        return $this->success($result);
    }

    public function index(Request $request)
    {
        // 新订单( 10 new)、待抢单(20 pending)、待取货(30 receiving)、配送中(40 delivering)、配送异常(50 exceptional)、取消/退款(60 refund)、催单(70 remind)
        $status = (int) $request->get('status', '');
        $page_size = $request->get('page_size', 10);
        if (!in_array($status, [10,20,30,40,50,60,70])) {
            $status = 10;
        }
        $query = Order::with(['products' => function ($query) {
            $query->select('order_id', 'food_name', 'spec', 'upc', 'quantity', 'price');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type',
                'money', 'updated_at','delivery_name','delivery_phone');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status');
        }])->select('id','order_id','wm_id','shop_id','wm_poi_name','receiver_name','receiver_phone','receiver_address','receiver_lng','receiver_lat',
            'caution','day_seq','platform','status','created_at', 'ps as logistic_type','push_at','receive_at','take_at','over_at','cancel_at',
            'courier_name', 'courier_phone')
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
            $query->where('status', '<', 70)->where('remind_num', '>', 0);
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
                // 电话列表
                $order->receiver_phone_list = [$order->receiver_phone];
                // 订单商品数量
                $order->product_num = 0;
                // 订单商户实收
                $order->poi_receive = 0;
                // 预约单
                $order->delivery_time = 0;
                // 收货尾号
                $order->receiver_phone_end = '';
                // 订单标题
                $order->title = $this->setOrderListTitle($status, $order);
                // 状态描述
                $order->status_title = '';
                $order->status_description = '';
                if (in_array($order->status, [20,50,60,70])) {
                    $order->status_title = OrderDelivery::$delivery_status_order_list_title_map[$order->status] ?? '其它';
                    if ($order->status === 20) {
                        $order->status_description = '下单成功';
                    } else {
                        $status_description_platform = OrderDelivery::$delivery_platform_map[$order->logistic_type];
                        $order->status_description = "[{$status_description_platform}] {$order->courier_name} {$order->courier_phone}";
                    }
                }
                preg_match_all('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, $preg_result);
                if (!empty($preg_result[0][0])) {
                    $order->caution = preg_replace('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, '');
                }
                if (!empty($preg_result[1][0])) {
                    $order->receiver_phone_end = $preg_result[1][0];
                }
                // 商品信息
                if (!empty($order->products)) {
                    $product_num = 0;
                    foreach ($order->products as $product) {
                        $product_num += $product->quantity;
                        if ($product->upc) {
                            $product->image = $images[$product->upc] ?? '';
                        }
                    }
                    $order->product_num = $product_num;
                }
                // 外卖订单信息
                if (!empty($order->order)) {
                    $order->poi_receive = $order->order->poi_receive ?? 0;
                    $order->delivery_time = $order->order->delivery_time ?? 0;
                    unset($order->order);
                }
            }
        }
        return $this->page($orders);
    }

    public function setOrderListTitle($status, $order)
    {
        if ($status === 10) {
            if (!empty($order->order->delivery_time)) {
                return '<text class="time-text">预约订单，' . tranTime2($order->order->delivery_time) . '<text/>送达';
            } else {
                return '<text class="time-text">' . tranTime(strtotime($order->created_at)) . '</text>下单';
            }
        } elseif ($status === 20 && $order->push_at) {
            return '<text class="time-text">' . tranTime(strtotime($order->push_at)) . '</text>发单';
        } elseif ($status === 30 && $order->receive_at) {
            return '<text class="time-text">' . tranTime(strtotime($order->receive_at)) . '</text>接单';
        } elseif ($status === 40) {
            if (!empty($order->order->delivery_time)) {
                return '<text class="time-text">预约订单，' . tranTime2($order->order->delivery_time) . '<text/>送达' . tranTime3($order->order->delivery_time);
            } elseif (!empty($order->order->estimate_arrival_time)) {
                return '<text class="time-text">' . tranTime2($order->order->estimate_arrival_time) . '</text>送达' . tranTime3($order->order->estimate_arrival_time);
            }
        }
        if (!empty($order->order->delivery_time)) {
            return '<text class="time-text">预约订单，' . date("m-d H:i", $order->order->delivery_time) . '<text/>送达';
        } else {
            return '<text class="time-text">立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
    }

    public function show(Request $request)
    {
        if (!$order = Order::select('id','order_id','wm_id','shop_id','wm_poi_name','receiver_name','receiver_phone','receiver_address','receiver_lng','receiver_lat',
            'caution','day_seq','platform','status','created_at', 'ps as logistic_type','push_at','receive_at','take_at','over_at','cancel_at',
            'courier_name', 'courier_phone','courier_lng','courier_lat','money as shipping_fee')
            ->find(intval($request->get('id', 0)))) {
            return $this->error('订单不存在');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id'))) {
                return $this->error('订单不存在!');
            }
        }
        $order->load(['products' => function ($query) {
            $query->select('order_id', 'food_name', 'spec', 'upc', 'quantity','price');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type',
                'money', 'updated_at','delivery_name','delivery_phone');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status','original_price','total');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_lng','shop_lat');
        }]);

        // 电话列表
        $order->receiver_phone_list = [$order->receiver_phone];
        // 订单商品数量
        $order->product_num = 0;
        // 订单商户实收
        $order->total = 0;
        $order->original_price = 0;
        $order->poi_receive = 0;
        // 预约单
        $order->delivery_time = 0;
        // 收货尾号
        $order->receiver_phone_end = '';
        // 期望送达时间
        $order->delivery_time_text = '';
        // 状态描述
        $order->status_title = '';
        if (in_array($order->status, [20,50,60,70,75,99])) {
            $order->status_title = OrderDelivery::$delivery_status_order_info_title_map[$order->status] ?? '其它';
            $order->status_description = OrderDelivery::$delivery_status_order_info_description_map[$order->status] ?? '';
        } elseif ($order->status <= 10) {
            $order->status_title = '待配送';
            $order->status_description = '确认订单成功，请尽快安排制作';
        }
        // 正则匹配电话尾号，去掉默认备注
        preg_match_all('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, $preg_result);
        if (!empty($preg_result[0][0])) {
            $order->caution = preg_replace('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, '');
        }
        if (!empty($preg_result[1][0])) {
            $order->receiver_phone_end = $preg_result[1][0];
        }
        if (!empty($order->order->delivery_time)) {
            $order->title = '<text>预约订单，' . date("m-d H:i", $order->order->delivery_time) . '<text/>送达';
        } else {
            $order->title = '<text>立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
        // 商品图片
        $images = [];
        if (!empty($order->products)) {
            foreach ($order->products as $product) {
                if ($product->upc) {
                    $upcs[] = $product->upc;
                }
            }
        }
        if (!empty($upcs)) {
            $images = MedicineDepot::whereIn('upc', $upcs)->pluck('cover', 'upc');
        }
        // 商品信息
        if (!empty($order->products)) {
            $product_num = 0;
            foreach ($order->products as $product) {
                $product_num += $product->quantity;
                if ($product->upc) {
                    $product->image = $images[$product->upc] ?? '';
                }
            }
            $order->product_num = $product_num;
        }
        if (isset($order->order->delivery_time) && isset($order->order->estimate_arrival_time)) {
            if ($order->order->delivery_time) {
                $order->delivery_time_text = date("m-d H:i", $order->order->delivery_time);
            } else {
                $order->delivery_time_text = date("m-d H:i", $order->order->estimate_arrival_time);
            }
        }
        // 外卖订单信息
        if (!empty($order->order)) {
            $order->total = $order->order->total ?? 0;
            $order->original_price = $order->order->original_price ?? 0;
            $order->poi_receive = $order->order->poi_receive ?? 0;
            $order->delivery_time = $order->order->delivery_time ?? 0;
            unset($order->order);
        }
        // 地图坐标
        $user_location = [ 'type' => 'user', 'lng' => $order->receiver_lng, 'lat' => $order->receiver_lat, 'title' => '' ];
        $shop_location = [ 'type' => 'shop', 'lng' => $order->shop->shop_lng, 'lat' => $order->shop->shop_lat, 'title' => '' ];
        $delivery_location = [ 'type' => 'delivery', 'lng' => $order->courier_lng, 'lat' => $order->courier_lat, 'title' => '' ];
        if ($order->status <= 20) {
            $user_location['title'] = '距离门店' . get_distance_title($order->receiver_lng, $order->receiver_lat, $order->shop->shop_lng, $order->shop->shop_lat);
            $locations = [$user_location, $shop_location];
        } elseif ($order->status == 50) {
            $delivery_location['title'] = '距离门店' . get_distance_title($order->receiver_lng, $order->receiver_lat, $order->shop->shop_lng, $order->shop->shop_lat);
            $locations = [$user_location, $shop_location, $delivery_location];
        } elseif ($order->status == 60) {
            $delivery_location['title'] = '距离顾客' . get_distance_title($order->receiver_lng, $order->receiver_lat, $order->courier_lng, $order->courier_lat);
            $locations = [$user_location, $shop_location, $delivery_location];
        } else {
            $locations = [$user_location];
        }
        unset($order->shop);
        $order->locations = $locations;

        return $this->success($order);
    }

    public function cancel(Request $request)
    {
        return $this->success();
    }
}
