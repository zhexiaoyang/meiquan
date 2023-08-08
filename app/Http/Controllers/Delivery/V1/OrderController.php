<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\MedicineDepot;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * 订单统计
     * @data 2023/8/7 10:38 下午
     */
    public function statistics(Request $request)
    {
        $shop_id = $request->get('shop_id', '');
        $order_where = [['ignore', '=', 0], ['created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day'))],];
        $wm_order_where = [['created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day'))],];
        // $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
        // $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
        // // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
            $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')];
        }
        if ($shop_id) {
            $order_where[] = ['shop_id', '=', $shop_id];
            $wm_order_where[] = ['shop_id', '=', $shop_id];
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

    /**
     * 订单列表
     * @data 2023/8/7 10:39 下午
     */
    public function index(Request $request)
    {
        // 新订单( 10 new)、待抢单(20 pending)、待取货(30 receiving)、配送中(40 delivering)、配送异常(50 exceptional)、取消/退款(60 refund)、催单(70 remind)
        $status = (int) $request->get('status', '');
        $page_size = $request->get('page_size', 10);
        $shop_id = $request->get('shop_id', '');
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
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
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

    /**
     * 订单标题方法
     * @data 2023/8/7 10:39 下午
     */
    public function setOrderListTitle($status, $order)
    {
        if ($status === 10) {
            if (!empty($order->order->delivery_time)) {
                return '<text class="time-text" style="color: #5ac725">预约订单，' . tranTime2($order->order->delivery_time) . '<text/>送达';
            } else {
                return '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->created_at)) . '</text>下单';
            }
        } elseif ($status === 20 && $order->push_at) {
            return '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->push_at)) . '</text>发单';
        } elseif ($status === 30 && $order->receive_at) {
            return '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->receive_at)) . '</text>接单';
        } elseif ($status === 40) {
            if (!empty($order->order->delivery_time)) {
                return '<text class="time-text" style="color: #5ac725">预约订单，' . tranTime2($order->order->delivery_time) . '<text/>送达' . tranTime3($order->order->delivery_time);
            } elseif (!empty($order->order->estimate_arrival_time)) {
                return '<text class="time-text" style="color: #5ac725">' . tranTime2($order->order->estimate_arrival_time) . '</text>送达' . tranTime3($order->order->estimate_arrival_time);
            }
        }
        if (!empty($order->order->delivery_time)) {
            return '<text class="time-text" style="color: #5ac725">预约订单，' . date("m-d H:i", $order->order->delivery_time) . '<text/>送达';
        } else {
            return '<text class="time-text" style="color: #5ac725">立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
    }

    /**
     * 订单详情
     * @data 2023/8/7 10:39 下午
     */
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
            $query->select('id', 'order_id', 'food_name', 'spec', 'upc', 'quantity','price');
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

    public function calculate(Request $request)
    {
        if (!$order = Order::find($request->get("order_id", 0))) {
            return $this->error("订单不存在");
        }
        // 判断权限
        //
        //
        //

        // 获取门店
        if (!$shop = Shop::find($order->shop_id)) {
            return $this->error("门店不存在");
        }
        $send_shop = $shop;
        // 默认设置
        $ss_switch = true;
        $dd_switch = true;
        $uu_switch = true;
        $sf_switch = true;
        $zb_switch = true;
        // 店铺发单设置
        if ($setting = OrderSetting::where("shop_id", $shop->id)->first()) {
            // 仓库发单
            if ($setting->warehouse && $setting->warehouse_time && ($setting->warehouse !== $shop->id)) {
                $time_data = explode('-', $setting->warehouse_time);
                if (!empty($time_data) && (count($time_data) === 2)) {
                    if (in_time_status($time_data[0], $time_data[1])) {
                        $send_shop = Shop::find($setting->warehouse);
                    }
                }
            }
            if ($shop->id != $send_shop->id) {
                $setting = OrderSetting::where("shop_id", $setting->warehouse)->first();
            }
            $ss_switch = $setting->shansong;
            $dd_switch = $setting->dada;
            $uu_switch = $setting->uu;
            $sf_switch = $setting->shunfeng;
            $zb_switch = $setting->zhongbao;
        }

        // 加价金额
        $add_money = $send_shop->running_add;
        // 自主运力
        $shippers = $send_shop->shippers;
        $shipper_platform_data = [];
        if (!empty($shippers)) {
            foreach ($shippers as $shipper) {
                $shipper_platform_data[] = $shipper->platform;
            }
        }

        // 设置返回参数
        $result = [];
        // 最便宜价格
        $min_money = 0;
        // 返回item格式
        $item = [
            'platform' => '闪送',
            'price' => 7.2,
            'distance' => '123662米',
            'description' => '已减2.70元',
            'status' => 1, // 1 可选，0 不可选
            'tag' => '一对一送'
        ];

        // ---------------------计算发单价格---------------------
        // 闪送价格计算
        if (!$send_shop->shop_id_ss) {
            \Log::info('门店未开通闪送');
        } elseif (!$ss_switch) {
            \Log::info('门店关闭闪送发单');
        } else {
            if (in_array(3, $shipper_platform_data)) {
                // 自有闪送
                $shansong = new ShanSongService(config('ps.shansongservice'));
                $ss_add_money = 0;
            } else {
                // 聚合闪送
                $shansong = app("shansong");
                $ss_add_money = $add_money;
            }
            $check_ss = $shansong->orderCalculate($shop, $order);
            if (isset($check_ss['status']) && $check_ss['status'] == 200 && !empty($check_ss['data'])) {
                $ss_money = sprintf("%.2f", ($check_ss['data']['totalFeeAfterSave'] / 100) + $ss_add_money);
                $result['ss'] = [
                    'platform' => '闪送',
                    'price' => $ss_money,
                    'distance' => get_kilometre($check_ss['data']['totalDistance']),
                    'description' => !empty($check_ss['data']['couponSaveFee']) ? '已减' . $check_ss['data']['couponSaveFee'] / 100 . '元' : '',
                    'status' => 1, // 1 可选，0 不可选
                    'tag' => '一对一送'
                ];
                $min_money = $ss_money;
            } else {
                $result['ss'] = [
                    'platform' => '闪送',
                    'price' => '',
                    'distance' => '',
                    'description' => $check_ss['msg'] ?? '闪送校验失败',
                    'status' => 0, // 1 可选，0 不可选
                    'tag' => '一对一送'
                ];
                \Log::info('门店闪送发单失败', [$check_ss]);
            }
        }
        // 达达价格计算
        if (!$send_shop->shop_id_dd) {
            \Log::info('门店未开通达达');
        } elseif (!$dd_switch) {
            \Log::info('门店关闭达达发单');
        } else {
            if (in_array(5, $shipper_platform_data)) {
                // 自有达达
                $config = config('ps.dada');
                $config['source_id'] = get_dada_source_by_shop($send_shop->id);
                $dada = new DaDaService($config);
                $dd_add_money = 0;
            } else {
                // 聚合达达
                $dada = app("dada");
                $dd_add_money = $add_money;
            }
            $check_dd= $dada->orderCalculate($shop, $order);
            if (isset($check_dd['code']) && $check_dd['code'] == 0 && !empty($check_dd['result'])) {
                $dd_money = sprintf("%.2f", $check_dd['result']['fee'] + $dd_add_money);
                $result['dd'] = [
                    'platform' => '达达',
                    'price' => $dd_money,
                    'distance' => get_kilometre($check_dd['result']['distance']),
                    'description' => !empty($check_dd['result']['couponFee']) ? '已减' . $check_dd['data']['couponFee'] . '元' : '',
                    'status' => 1, // 1 可选，0 不可选
                    'tag' => ''
                ];
                if ($dd_money < $min_money) {
                    $min_money = $dd_money;
                }
            } else {
                $result['dd'] = [
                    'platform' => '达达',
                    'price' => '',
                    'distance' => '',
                    'description' => $check_dd['msg'] ?? '达达校验失败',
                    'status' => 0, // 1 可选，0 不可选
                    'tag' => ''
                ];
                \Log::info('门店达达发单失败', [$check_dd]);
            }
        }
        // 顺丰价格计算
        if (!$send_shop->shop_id_sf) {
            \Log::info('门店未开通顺丰');
        } elseif (!$sf_switch) {
            \Log::info('门店关闭顺丰发单');
        } else {
            if (in_array(7, $shipper_platform_data)) {
                // 自有顺丰
                $shunfeng = app("shunfengservice");
                $sf_add_money = 0;
            } else {
                // 聚合顺丰
                $shunfeng = app("shunfeng");
                $sf_add_money = $add_money;
            }
            $check_sf= $shunfeng->precreateorder($order, $shop);
            if (isset($check_sf['error_code']) && $check_sf['error_code'] == 0 && !empty($check_sf['result'])) {
                $sf_money = sprintf("%.2f", ($check_sf['result']['real_pay_money'] / 100) + $sf_add_money);
                $result['sf'] = [
                    'platform' => '顺丰',
                    'price' => $sf_money,
                    'distance' => get_kilometre($check_sf['result']['delivery_distance_meter']),
                    'description' => !empty($check_sf['result']['coupons_total_fee']) ? '已减' . $check_sf['data']['coupons_total_fee'] . '元' : '',
                    'status' => 1, // 1 可选，0 不可选
                    'tag' => ''
                ];
                if ($sf_money < $min_money) {
                    $min_money = $sf_money;
                }
            } else {
                $result['sf'] = [
                    'platform' => '顺丰',
                    'price' => '',
                    'distance' => '',
                    'description' => $check_sf['msg'] ?? '顺丰校验失败',
                    'status' => 0, // 1 可选，0 不可选
                    'tag' => ''
                ];
                \Log::info('门店顺丰发单失败', [$check_sf]);
            }
        }
        // UU价格计算
        if (!$send_shop->shop_id_uu) {
            \Log::info('门店未开通UU');
        } elseif (!$uu_switch) {
            \Log::info('门店关闭UU发单');
        } else {
            $uu = app("uu");
            $check_uu= $uu->orderCalculate($order, $shop);
            if (isset($check_uu['return_code']) && $check_uu['return_code'] == 'ok' && !empty($check_uu['result'])) {
                $sf_money = sprintf("%.2f", ($check_uu['result']['real_pay_money'] / 100) + $sf_add_money);
                $result['sf'] = [
                    'platform' => '顺丰',
                    'price' => $sf_money,
                    'distance' => get_kilometre($check_uu['result']['delivery_distance_meter']),
                    'description' => !empty($check_uu['result']['coupons_total_fee']) ? '已减' . $check_uu['data']['coupons_total_fee'] . '元' : '',
                    'status' => 1, // 1 可选，0 不可选
                    'tag' => ''
                ];
                if ($sf_money < $min_money) {
                    $min_money = $sf_money;
                }
            } else {
                $result['sf'] = [
                    'platform' => '顺丰',
                    'price' => '',
                    'distance' => '',
                    'description' => $check_uu['msg'] ?? '顺丰校验失败',
                    'status' => 0, // 1 可选，0 不可选
                    'tag' => ''
                ];
                \Log::info('门店顺丰发单失败', [$check_uu]);
            }
        }

    }
}
