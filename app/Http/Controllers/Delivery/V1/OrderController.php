<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Handlers\AddressRecognitionHandler;
use App\Http\Controllers\Controller;
use App\Jobs\CreateMtOrder;
use App\Jobs\PrintWaiMaiOrder;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\MedicineDepot;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Models\WmPrinter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
        // $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
        // $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
        // // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
            $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
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

    public function search(Request $request)
    {
        return $this->success();
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
        $source = (int) $request->get('source', 0);
        $order_by = $request->get('order', 0);
        if (!in_array($status, [10,20,30,40,50,60,70])) {
            $status = 10;
        }
        $query = Order::with(['products' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'spec', 'upc', 'quantity','price');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type',
                'money', 'updated_at','delivery_name','delivery_phone');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_name');
        }])->select('id','order_id','wm_id','shop_id','wm_poi_name','receiver_name','receiver_phone','receiver_address','receiver_lng','receiver_lat',
            'caution','day_seq','platform','status','created_at', 'ps as logistic_type','push_at','receive_at','take_at','over_at','cancel_at',
            'courier_name', 'courier_phone')
            ->where('ignore', 0)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day')));
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id')->toArray());
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
        // 订单来源-开始
        if ($source === 1) {
            // 美团
            $query->where('platform', 1);
        } elseif ($source === 2) {
            // 饿了么
            $query->where('platform', 2);
        } elseif ($source === 10) {
            // 其它 （0 手动创建， 11 药柜）
            $query->whereIn('platform', [0, 11]);
        }
        // 订单来源-结束
        // 排序-开始
        if ($order_by === 'receive_desc') {
            // $query->leftJoin('wm_orders', 'orders.wm_id', '=', 'wm_orders.id')->orderByDesc('wm_orders.poi_receive');
            $query->with(['order' => function ($query) use ($order_by) {
                Log::info('123123123');
                $query->orderByDesc('poi_receive');
            }]);
        } elseif ($order_by === 'receive_asc') {
            $query->with(['order' => function ($query) use ($order_by) {
                $query->orderBy('poi_receive');
            }]);
        } elseif ($order_by === 'create_asc') {
            $query->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }
        // 排序-结束
        $orders = $query->paginate($page_size);
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
                $order->title = Order::setAppOrderListTitle($status, $order->order->delivery_time ?? 0, $order->order->estimate_arrival_time ?? 0, $order);
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
                }
                if (!$order->wm_poi_name) {
                    $order->wm_poi_name = $order->shop->shop_name ?? '';
                }
                unset($order->order);
                unset($order->shop);
            }
        }
        return $this->page($orders);
    }

    /**
     * 搜索订单列表
     * @data 2023/8/7 10:39 下午
     */
    public function searchList(Request $request)
    {
        $search_key = $request->get('search_key', '');
        if (empty($search_key)) {
            return $this->success();
        }
        // 10 流水号，20 顾客手机号，30 配送员手机号，40 订单编号
        $search_type = (int) $request->get('search_type', '');
        if (!in_array($search_type, [10,20,30,40])) {
            return $this->error('搜索类型错误');
        }
        $page_size = $request->get('page_size', 10);
        $query = Order::with(['products' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'spec', 'upc', 'quantity','price');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type',
                'money', 'updated_at','delivery_name','delivery_phone');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_name');
        }])->select('id','order_id','wm_id','shop_id','wm_poi_name','receiver_name','receiver_phone','receiver_address','receiver_lng','receiver_lat',
            'caution','day_seq','platform','status','created_at', 'ps as logistic_type','push_at','receive_at','take_at','over_at','cancel_at',
            'courier_name', 'courier_phone');
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id')->toArray());
        }
        if ($search_type === 10) {
            $query->where('day_seq', $search_key);
        } elseif ($search_type === 40) {
            $query->where('order_id', $search_key);
        }
        // 查询订单
        $orders = $query->paginate($page_size);
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
                $order->title = Order::setAppSearchOrderTitle($order->order->delivery_time ?? 0, $order->order->estimate_arrival_time ?? 0, $order);
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
                }
                if (!$order->wm_poi_name) {
                    $order->wm_poi_name = $order->shop->shop_name ?? '';
                }
                unset($order->order);
                unset($order->shop);
            }
        }
        return $this->page($orders);
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
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
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
            $query->select('id', 'shop_lng','shop_lat','shop_name');
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
        $order->title = Order::setAppOrderInfoTitle($order->order->delivery_time ?? 0, $order);
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
        if (!$order->wm_poi_name) {
            $order->wm_poi_name = $order->shop->shop_name ?? '';
        }
        unset($order->shop);
        $order->locations = $locations;

        return $this->success($order);
    }

    /**
     * 创建订单
     * @data 2023/8/10 5:17 下午
     */
    public function store(Request $request)
    {
        $shop_id = $request->get('shop_id', 0);

        if (!$shop = Shop::select('id', 'user_id', 'running_select')->find($shop_id)) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if (!in_array($shop->id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('门店不存在');
        }
        $create_order_shop_lock = Cache::lock("create_order_shop_lock" . $shop_id, 5);
        if (!$create_order_shop_lock->get()) {
            return $this->error('刚刚已经处下过单了，请稍后再试！');
        }

        $order_data = ['shop_id' => $shop_id, 'user_id' => $shop->user_id];
        // 接收参数
        if (!$receiver_name = $request->get('receiver_name', '')) {
            return $this->error('收货人姓名不能为空');
        }
        if (strlen($receiver_name) > 10) {
            return $this->error('收货人姓名长度不能大于10个汉字');
        }
        $order_data['receiver_name'] = $receiver_name;
        // ------
        if (!$receiver_phone = $request->get('receiver_phone', '')) {
            return $this->error('收货人手机号不能为空');
        }
        if (strlen($receiver_phone) !== 11) {
            return $this->error('收货人手机号格式不正确');
        }
        $tmp_number = $request->get('tmp_number', '');
        if ($tmp_number) {
            if (strlen($tmp_number) > 4) {
                return $this->error('临时号格式不正确');
            }
            $receiver_phone .= '_' . $tmp_number;
        }
        $order_data['receiver_phone'] = $receiver_phone;
        // ------
        $receiver_lng = $request->get('receiver_lng', '');
        $receiver_lat = $request->get('receiver_lat', '');
        if (!$receiver_lng || !$receiver_lat) {
            return $this->error('收货人经纬度不能为空');
        }
        $order_data['receiver_lng'] = $receiver_lng;
        $order_data['receiver_lat'] = $receiver_lat;
        // ------
        if (!$receiver_address = $request->get('receiver_address', '')) {
            return $this->error('收货人地址不能为空');
        }
        // ------
        if (!$house_number = $request->get('house_number', '')) {
            return $this->error('收货人门牌号不能为空');
        }
        $order_data['receiver_address'] = $receiver_address . '，' .$house_number;
        // ------caution
        $caution = $request->get('caution', '');
        if (strlen($caution) > 100) {
            return $this->error('备注不能超过100字');
        }
        $order_data['caution'] = $caution;
        $order_data['status'] = 0;

        $order = Order::create($order_data);
        OrderLog::create([
            "order_id" => $order->id,
            "des" => "手动创建跑腿订单",
            "user_id" => $user->id
        ]);
        Shop::where(['user_id' => $shop->user_id])->update(['running_select' => 0]);
        $shop->running_select = 1;
        $shop->save();
        return $this->success(['id' => $order->id, 'order_id' => $order->order_id]);
    }

    /**
     * 忽略订单
     * @data 2023/8/10 9:19 上午
     */
    public function ignore(Request $request)
    {
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        $_rand = rand(1, 2);
        if ($_rand === 1) {
            return $this->message('忽略成功');
        } else {
            return $this->error('忽略失败');
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        return $this->success();
    }

    /**
     * 取消订单
     * @data 2023/8/10 9:20 上午
     */
    public function cancel(Request $request)
    {
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 如果订单状态是已接单状态，不发单
        if ($order->status == 99) {
            return $this->success();
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        $_rand = rand(1, 2);
        if ($_rand === 1) {
            return $this->message('取消成功');
        } else {
            return $this->error('取消失败');
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        return $this->success();
    }

    /**
     * 配送下单-计算配送费
     * @data 2023/8/8 2:54 下午
     */
    public function calculate(Request $request)
    {
        $order = Order::select('id','order_id','shop_id','day_seq','platform','status','wm_poi_name','receiver_name',
            'receiver_phone','receiver_address','receiver_lng','receiver_lat','created_at','wm_id')
            ->find($request->get("order_id", 0));
        if (!$order) {
            return $this->error("订单不存在");
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }

        // 获取门店
        if (!$shop = Shop::find($order->shop_id)) {
            return $this->error("门店不存在");
        }
        if ($order->wm_id) {
            $wm_order = WmOrder::select('id', 'delivery_time')->find($order->wm_id);
        }
        $tip = $request->get('tip', 0);
        if (!is_numeric($tip)) {
            $tip = 0;
        }
        $tip = (float) sprintf("%.1f", $tip);
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
        $min_money = 100;
        // 返回item格式
        // $item = [
        //     'platform' => '闪送',
        //     'price' => 7.2,
        //     'distance' => '123662米',
        //     'description' => '已减2.70元',
        //     'status' => 1, // 1 可选，0 不可选
        //     'tag' => '一对一送'
        // ];
        // 查询已经发单的记录
        $deliveries = $order->deliveries;
        $send_platform_data = [];
        if (!empty($deliveries)) {
            foreach ($deliveries as $delivery) {
                if ($delivery->status < 99) {
                    $send_platform_data[$delivery->platform] = $delivery;
                }
            }
        }
        // ---------------------计算发单价格---------------------
        // 闪送价格计算
        if (isset($send_platform_data[3])) {
            $result['ss'] = [
                'platform' => 3,
                'platform_name' => '闪送',
                'price' => $send_platform_data[3]->money,
                'distance' => '',
                'description' => '',
                'error_status' => 0,
                'error_msg' => '',
                'status' => 0, // 1 可选，0 不可选
                'checked' => 0,
                'tag' => OrderDelivery::$delivery_status_order_info_title_map[$send_platform_data[3]->status]
            ];
        } elseif (!$send_shop->shop_id_ss && !in_array(3, $shipper_platform_data)) {
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
            $check_ss = $shansong->orderCalculate($send_shop, $order, $tip);
            if (isset($check_ss['status']) && $check_ss['status'] == 200 && !empty($check_ss['data'])) {
                $ss_money = sprintf("%.2f", ($check_ss['data']['totalFeeAfterSave'] / 100) + $ss_add_money);
                $result['ss'] = [
                    'platform' => 3,
                    'platform_name' => '闪送',
                    'price' => $ss_money,
                    'distance' => get_kilometre($check_ss['data']['totalDistance']),
                    'description' => !empty($check_ss['data']['couponSaveFee']) ? '已减' . $check_ss['data']['couponSaveFee'] / 100 . '元' : '',
                    'error_status' => 0,
                    'error_msg' => '',
                    'status' => 1, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => '一对一送'
                ];
                $min_money = $ss_money;
            } else {
                $result['ss'] = [
                    'platform' => 3,
                    'platform_name' => '闪送',
                    'price' => '',
                    'distance' => '',
                    'description' => '计价失败',
                    'error_status' => 1,
                    'error_msg' => $check_ss['msg'] ?? '无法下单',
                    'status' => 0, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => '一对一送'
                ];
                \Log::info('门店闪送发单失败', [$check_ss]);
            }
        }
        // 达达价格计算
        if (isset($send_platform_data[5])) {
            $result['dd'] = [
                'platform' => 5,
                'platform_name' => '达达',
                'price' => $send_platform_data[5]->money,
                'distance' => '',
                'description' => '',
                'error_status' => 0,
                'error_msg' => '',
                'status' => 0, // 1 可选，0 不可选
                'checked' => 0,
                'tag' => OrderDelivery::$delivery_status_order_info_title_map[$send_platform_data[5]->status]
            ];
        } elseif (!$send_shop->shop_id_dd && !in_array(5, $shipper_platform_data)) {
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
            $check_dd= $dada->orderCalculate($send_shop, $order, $tip);
            if (isset($check_dd['code']) && $check_dd['code'] == 0 && !empty($check_dd['result'])) {
                $dd_money = sprintf("%.2f", $check_dd['result']['fee'] + $check_dd['result']['tips'] + $dd_add_money);
                $result['dd'] = [
                    'platform' => 5,
                    'platform_name' => '达达',
                    'price' => $dd_money,
                    'distance' => get_kilometre($check_dd['result']['distance']),
                    'description' => !empty($check_dd['result']['couponFee']) ? '已减' . $check_dd['result']['couponFee'] . '元' : '',
                    'error_status' => 0,
                    'error_msg' => '',
                    'status' => 1, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                if ($dd_money < $min_money) {
                    $min_money = $dd_money;
                }
            } else {
                $result['dd'] = [
                    'platform' => 5,
                    'platform_name' => '达达',
                    'price' => '',
                    'distance' => '',
                    'description' => '计价失败',
                    'error_status' => 1,
                    'error_msg' => $check_dd['msg'] ?? '无法下单',
                    'status' => 0, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                \Log::info('门店达达发单失败', [$check_dd]);
            }
        }
        // 顺丰价格计算
        if (isset($send_platform_data[7])) {
            $result['sf'] = [
                'platform' => 7,
                'platform_name' => '顺丰',
                'price' => $send_platform_data[7]->money,
                'distance' => '',
                'description' => '',
                'error_status' => 0,
                'error_msg' => '',
                'status' => 0, // 1 可选，0 不可选
                'checked' => 0,
                'tag' => OrderDelivery::$delivery_status_order_info_title_map[$send_platform_data[7]->status]
            ];
        } elseif (!$send_shop->shop_id_sf && !in_array(7, $shipper_platform_data)) {
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
            $check_sf= $shunfeng->precreateorder($order, $send_shop, $tip);
            if (isset($check_sf['error_code']) && $check_sf['error_code'] == 0 && !empty($check_sf['result'])) {
                $sf_money = sprintf("%.2f", ($check_sf['result']['real_pay_money'] / 100) + $sf_add_money);
                $result['sf'] = [
                    'platform' => 7,
                    'platform_name' => '顺丰',
                    'price' => $sf_money,
                    'distance' => get_kilometre($check_sf['result']['delivery_distance_meter']),
                    'description' => !empty($check_sf['result']['coupons_total_fee']) ? '已减' . $check_sf['result']['coupons_total_fee'] . '元' : '',
                    'error_status' => 0,
                    'error_msg' => '',
                    'status' => 1, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                if ($sf_money < $min_money) {
                    $min_money = $sf_money;
                }
            } else {
                $result['sf'] = [
                    'platform' => 7,
                    'platform_name' => '顺丰',
                    'price' => '',
                    'distance' => '',
                    'description' => '计价失败',
                    'error_status' => 1,
                    'error_msg' => $check_sf['msg'] ?? '无法下单',
                    'status' => 0, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                \Log::info('门店顺丰发单失败', [$check_sf]);
            }
        }
        // UU价格计算
        if (isset($send_platform_data[6])) {
            $result['uu'] = [
                'platform' => 6,
                'platform_name' => 'UU',
                'price' => $send_platform_data[6]->money,
                'distance' => '',
                'description' => '',
                'error_status' => 0,
                'error_msg' => '',
                'status' => 0, // 1 可选，0 不可选
                'checked' => 0,
                'tag' => OrderDelivery::$delivery_status_order_info_title_map[$send_platform_data[6]->status]
            ];
        } elseif (!$send_shop->shop_id_uu) {
            \Log::info('门店未开通UU');
        } elseif (!$uu_switch) {
            \Log::info('门店关闭UU发单');
        } else {
            $uu = app("uu");
            $check_uu= $uu->orderCalculate($order, $send_shop);
            if (isset($check_uu['return_code']) && $check_uu['return_code'] == 'ok') {
                $uu_money = sprintf("%.2f", $check_uu['need_paymoney'] + $add_money);
                $result['uu'] = [
                    'platform' => 6,
                    'platform_name' => 'UU',
                    'price' => $uu_money,
                    'distance' => get_kilometre($check_uu['distance']),
                    'description' => !empty($check_uu['total_priceoff']) ? '已减' . $check_uu['total_priceoff'] . '元' : '',
                    'error_status' => 0,
                    'error_msg' => '',
                    'status' => 1, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                if ($uu_money < $min_money) {
                    $min_money = $uu_money;
                }
            } else {
                $result['uu'] = [
                    'platform' => 6,
                    'platform_name' => 'UU',
                    'price' => '',
                    'distance' => '',
                    'description' => '计价失败',
                    'error_status' => 1,
                    'error_msg' => $check_uu['return_msg'] ?? '无法下单',
                    'status' => 0, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                \Log::info('门店UU发单失败', [$check_uu]);
            }
        }
        // 众包价格计算
        if (isset($send_platform_data[8])) {
            $result['zb'] = [
                'platform' => 8,
                'platform_name' => '美团众包',
                'price' => $send_platform_data[8]->money,
                'distance' => '',
                'description' => '',
                'error_status' => 0,
                'error_msg' => '',
                'status' => 0, // 1 可选，0 不可选
                'checked' => 0,
                'tag' => OrderDelivery::$delivery_status_order_info_title_map[$send_platform_data[8]->status]
            ];
        } elseif (!in_array($shop->meituan_bind_platform, [4, 31])) {
            $this->log("门店未绑定民康、闪购，停止众包派单");
        } elseif (!$shop->shop_id_zb) {
            $this->log("未开通众包，停止「美团众包」派单");
        } elseif ($order->shop_id != $send_shop->id) {
            \Log::info('转仓库订单，停止「美团众包」派单');
        } elseif (!$send_shop->shop_id_zb) {
            \Log::info('门店未开通美团众包');
        } elseif (!$uu_switch) {
            \Log::info('门店关闭美团众包发单');
        } else {
            if ($shop->meituan_bind_platform == 4) {
                $meituan_shop_id = '';
                $zhongbaoapp = app('minkang');
            } elseif ($shop->meituan_bind_platform == 31) {
                $meituan_shop_id = $shop->waimai_mt;
                $zhongbaoapp = app('meiquan');
            }
            $check_zb= $zhongbaoapp->zhongBaoShippingFee($order->order_id, $meituan_shop_id);
            if (isset($check_zb['data']) && !empty($check_zb['data'])) {
                $zb_money = sprintf("%.2f", $check_zb['data'][0]['shipping_fee']);
                $deliveryFeeStr = $check_zb['data'][0]['deliveryFeeStr'] ?? '';
                $distance = '';
                if ($deliveryFeeStr) {
                    $deliveryFeeStr_data = json_decode($deliveryFeeStr, true);
                    $distance = $deliveryFeeStr_data['distance'] ?? '';
                }
                $result['zb'] = [
                    'platform' => 8,
                    'platform_name' => '美团众包',
                    'price' => $zb_money,
                    'distance' => $distance ? $distance . '公里' : '',
                    'description' => '',
                    'error_status' => 0,
                    'error_msg' => '',
                    'status' => 1, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                if ($zb_money < $min_money) {
                    $min_money = $zb_money;
                }
            } else {
                $result['zb'] = [
                    'platform' => 8,
                    'platform_name' => '美团众包',
                    'price' => '',
                    'distance' => '',
                    'description' => '计价失败',
                    'error_status' => 1,
                    'error_msg' => $check_zb['msg'] ?? '无法下单',
                    'status' => 0, // 1 可选，0 不可选
                    'checked' => 0,
                    'tag' => ''
                ];
                \Log::info('门店美团众包发单失败', [$check_zb]);
            }
        }

        foreach ($result as $k => $v) {
            if ($v['price'] == $min_money) {
                $result[$k]['tag'] = '最便宜';
                $result[$k]['checked'] = 1;
            }
        }
        if (!empty($wm_order->delivery_time)) {
            $order_title = '<text class="time-text" style="color: #5ac725">预约订单，' . date("m-d H:i", $order->order->delivery_time) . '</text>送达';
        } else {
            $order_title = '<text class="time-text" style="color: #5ac725">立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
        $res_data = [
            'id' => $order->id,
            'order_title' => $order_title,
            'shop_id' => $order->shop_id,
            'day_seq' => $order->day_seq,
            'platform' => $order->platform,
            'wm_poi_name' => $order->wm_poi_name,
            'delivery_time' => $wm_order->delivery_time ?? 0,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'receiver_address' => $order->receiver_address,
            'receiver_lng' => $order->receiver_lng,
            'receiver_lat' => $order->receiver_lat,
            'created_at' => date("Y-m-d H:i:s", strtotime($order->created_at)),
            'deliveries' => array_values($result)
        ];
        return $this->success($res_data);
    }

    /**
     * 派单
     * @data 2023/8/9 5:26 下午
     */
    public function send(Request $request)
    {
        if (!$platform = (int) $request->get('platform', 0)) {
            return $this->error('请选择配送平台');
        }
        $order_id = (int) $request->get("order_id", 0);
        if (!Redis::setnx("reset_order_id_" . $order_id, $order_id)) {
            return $this->error("刚刚已经发过单了，请稍后再试");
        }
        Redis::expire("reset_order_id_" . $order_id, 3);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 如果订单状态是已接单状态，不发单
        if ($order->status > 20 && $order->status < 99) {
            return $this->error("订单已被接单，不能继续派单");
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        // 获取门店
        if (!$shop = Shop::find($order->shop_id)) {
            return $this->error("门店不存在");
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        $_rand = rand(1, 2);
        if ($_rand === 1) {
            return $this->message('派单成功');
        } else {
            return $this->error('派单失败');
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        // 记录发单操作人
        Log::info("{配送发单:$order->order_id}|操作人：{$request->user()->id}|平台:{$platform}");
        // 默认发单门店是订单所属门店
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
        // 查询已经发单的记录
        $deliveries = OrderDelivery::select('id','status')->where('order_id', $order->id)->where('status', '<', 99)->get();
        $send_platform_data = [];
        if (!empty($deliveries)) {
            foreach ($deliveries as $delivery) {
                if ($delivery->status < 99) {
                    $send_platform_data[$delivery->platform] = $delivery;
                }
            }
        }
        // ----------------配送发单----------------
        // 判断刚刚是否发过配送订单
        // 判断是否接单了
        $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 1);
        if (!$jiedan_lock->get()) {
            return $this->error('已经操作接单，停止派单');
        }
        $jiedan_lock->release();
        //
        if ($platform === 3) {
            // 闪送
            if (isset($send_platform_data[3])) {
                return $this->error('闪送已经发过配送单了');
            } elseif (!$send_shop->shop_id_ss && !in_array(3, $shipper_platform_data)) {
                return $this->error('门店未开通闪送跑腿');
            } elseif (!$ss_switch) {
                return $this->error('门店关闭闪送跑腿');
            } else {
                $zy_ss = in_array(3, $shipper_platform_data);
                if ($zy_ss) {
                    // 自有闪送
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                    $ss_add_money = 0;
                } else {
                    // 聚合闪送
                    $shansong = app("shansong");
                    $ss_add_money = $add_money;
                }
                $check_ss = $shansong->orderCalculate($send_shop, $order);
                if (!empty($check_ss['data']['orderNumber'])) {
                    return $this->error('闪送发单失败' . !empty($check_ss['msg']) ? ':'.$check_ss['msg'] : '');
                }
                // 计算配送费返回闪送订单号
                $ss_order_id = $check_ss['data']['orderNumber'];
                $result_ss = $shansong->createOrderByOrderNo($ss_order_id);
                if (isset($result_ss['status']) && $result_ss['status'] == 200 && !empty($result_ss['data'])) {
                    $ss_money = sprintf("%.2f", ($result_ss['data']['totalFeeAfterSave'] / 100) + $ss_add_money);
                    // 订单发送成功
                    $this->log("发送「闪送」订单成功|返回参数", [$result_ss]);
                    $update_info = [
                        'money_ss' => $ss_money,
                        'shipper_type_ss' => $zy_ss ? 1 : 0,
                        'ss_order_id' => $ss_order_id,
                        'ss_status' => 20,
                        'status' => 20,
                        'push_at' => date("Y-m-d H:i:s")
                    ];
                    DB::table('orders')->where('id', $order->id)->update($update_info);
                    DB::table('order_logs')->insert([
                        'ps' => 3,
                        'order_id' => $order->id,
                        'des' => '「闪送」跑腿发单:' . $ss_order_id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    try {
                        DB::transaction(function () use ($order, $zy_ss, $result_ss, $ss_add_money, $ss_money) {
                            $delivery_id = DB::table('order_deliveries')->insertGetId([
                                'user_id' => $order->user_id,
                                'shop_id' => $order->shop_id,
                                'warehouse_id' => $order->warehouse_id,
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'order_no' => $order->order_id,
                                'three_order_no' => $result_ss['data']['orderNumber'] ?? '',
                                'platform' => 3,
                                'type' => $zy_ss ? 1 : 0,
                                'day_seq' => $order->day_seq,
                                'money' => $ss_money,
                                'add_money' => $zy_ss ? $ss_add_money : 0,
                                'original' => ($result_ss['data']['totalAmount'] ?? 0) / 100,
                                'coupon' => ($result_ss['data']['couponSaveFee'] ?? 0) / 100,
                                'distance' => $result_ss['data']['totalDistance'] ?? 0,
                                'weight' => $result_ss['data']['totalWeight'] ?? 0,
                                'status' => 20,
                                'track' => '待接单',
                                'send_at' => date("Y-m-d H:i:s"),
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                            DB::table('order_delivery_tracks')->insert([
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'delivery_id' => $delivery_id,
                                'status' => 20,
                                'status_des' => '下单成功',
                                'description' => '闪送单号：' . $result_ss['data']['orderNumber'] ?? '',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                        });
                    } catch (\Exception $exception) {
                        Log::info("闪送写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    }
                    return $this->success('闪送发单成功');
                } else {
                    return $this->error('闪送发单失败' . !empty($result_ss['msg']) ? ':'.$result_ss['msg'] : '');
                }
            }
        } elseif ($platform === 5) {
            // 达达
            if (isset($send_platform_data[5])) {
                return $this->error('达达已经发过配送单了');
            } elseif (!$send_shop->shop_id_dd && !in_array(5, $shipper_platform_data)) {
                return $this->error('门店未开通达达跑腿');
            } elseif (!$dd_switch) {
                return $this->error('门店关闭达达跑腿');
            } else {
                $zy_dd = in_array(5, $shipper_platform_data);
                if ($zy_dd) {
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
                if (!empty($check_dd['result']['deliveryNo'])) {
                    return $this->error('达达发单失败' . !empty($check_dd['msg']) ? ':'.$check_dd['msg'] : '');
                }
                // 计算配送费返回达达订单号
                $dada_order_id = $check_dd['result']['deliveryNo'];
                $result_dd = $dada->createOrder($dada_order_id);
                if (isset($result_dd['code']) && $result_dd['code'] == 0 && !empty($result_dd['result'])) {
                    $dd_money = sprintf("%.2f", $check_dd['result']['fee'] + $dd_add_money);
                    // 订单发送成功
                    $this->log("发送「达达」订单成功|返回参数", [$result_dd]);
                    // 写入订单信息
                    $update_info = [
                        'money_dd' => $dd_money,
                        'shipper_type_dd' => $zy_dd ? 1 : 0,
                        'dd_order_id' => $order->order_id,
                        'dd_status' => 20,
                        'status' => 20,
                        'push_at' => date("Y-m-d H:i:s")
                    ];
                    DB::table('orders')->where('id', $order->id)->update($update_info);
                    DB::table('order_logs')->insert([
                        'ps' => 5,
                        'order_id' => $order->id,
                        'des' => '「达达」跑腿发单',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    try {
                        DB::transaction(function () use ($order, $zy_dd, $check_dd, $dd_money, $dd_add_money) {
                            $delivery_id = DB::table('order_deliveries')->insertGetId([
                                'user_id' => $order->user_id,
                                'shop_id' => $order->shop_id,
                                'warehouse_id' => $order->warehouse_id,
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'order_no' => $order->order_id,
                                'three_order_no' => $order->order_id,
                                'platform' => 5,
                                'type' => $zy_dd ? 1 : 0,
                                'day_seq' => $order->day_seq,
                                'money' => $dd_money,
                                'add_money' => $zy_dd ? $dd_add_money : 0,
                                'original' => ($check_dd['result']['deliverFee'] ?? 0),
                                'coupon' => ($check_dd['result']['couponFee'] ?? 0),
                                'distance' => $check_dd['result']['distance'] ?? 0,
                                'weight' => 0,
                                'status' => 20,
                                'track' => '待接单',
                                'send_at' => date("Y-m-d H:i:s"),
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                            DB::table('order_delivery_tracks')->insert([
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'delivery_id' => $delivery_id,
                                'status' => 20,
                                'status_des' => '下单成功',
                                'description' => '达达单号：' . $order->order_id,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                        });
                    } catch (\Exception $exception) {
                        Log::info("达达写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    }
                    return $this->success('达达发单成功');
                } else {
                    return $this->error('达达发单失败' . !empty($result_dd['msg']) ? ':'.$result_dd['msg'] : '');
                }
            }
        } elseif ($platform === 6) {
            // UU
            if (isset($send_platform_data[6])) {
                return $this->error('UU已经发过配送单了');
            } elseif (!$send_shop->shop_id_uu && !in_array(6, $shipper_platform_data)) {
                return $this->error('门店未开通UU跑腿');
            } elseif (!$uu_switch) {
                return $this->error('门店关闭UU跑腿');
            } else {
                $uu = app("uu");
                $check_uu= $uu->orderCalculate($order, $send_shop);
                if (!empty($check_uu['price_token'])) {
                    return $this->error('UU发单失败' . !empty($check_uu['return_msg']) ? ':'.$check_uu['return_msg'] : '');
                }
                $uu_total_money = $check_uu['total_money'] ?? 0;
                $uu_need_paymoney = $check_uu['need_paymoney'] ?? 0;
                $uu_price_token = $check_uu['price_token'] ?? '';
                $result_uu = $uu->addOrderByToken($order, $shop, $uu_price_token, $uu_need_paymoney, $uu_total_money);
                if (isset($result_uu['return_code']) && $result_uu['return_code'] == 'ok') {
                    $uu_money = sprintf("%.2f", $uu_need_paymoney + $add_money);
                    // 写入订单信息
                    $update_info = [
                        'money_uu' => $uu_money,
                        'uu_order_id' => $result_uu['ordercode'],
                        'uu_status' => 20,
                        'status' => 20,
                        'push_at' => date("Y-m-d H:i:s")
                    ];
                    DB::table('orders')->where('id', $order->id)->update($update_info);
                    DB::table('order_logs')->insert([
                        'ps' => 6,
                        'order_id' => $order->id,
                        'des' => '「UU」跑腿发单',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    try {
                        DB::transaction(function () use ($order, $result_uu, $check_uu, $uu_money, $add_money) {
                            $delivery_id = DB::table('order_deliveries')->insertGetId([
                                'user_id' => $order->user_id,
                                'shop_id' => $order->shop_id,
                                'warehouse_id' => $order->warehouse_id,
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'order_no' => $order->order_id,
                                'three_order_no' => $result_uu['ordercode'] ?? '',
                                'platform' => 6,
                                'type' => 0,
                                'day_seq' => $order->day_seq,
                                'money' => ($check_uu['need_paymoney'] ?? 0),
                                'add_money' => $add_money,
                                'original' => ($check_uu['total_money'] ?? 0),
                                'coupon' => ($check_uu['coupon_amount'] ?? 0),
                                'addfee' => ($check_uu['addfee'] ?? 0),
                                'distance' => $check_uu['distance'] ?? 0,
                                'weight' => 0,
                                'status' => 20,
                                'track' => '待接单',
                                'send_at' => date("Y-m-d H:i:s"),
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                            DB::table('order_delivery_tracks')->insert([
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'delivery_id' => $delivery_id,
                                'status' => 20,
                                'status_des' => '下单成功',
                                'description' => 'UU单号：' . $result_uu['ordercode'] ?? '',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                        });
                    } catch (\Exception $exception) {
                        Log::info("UU写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    }
                    return $this->success('UU发单成功');
                } else {
                    return $this->error('UU发单失败' . !empty($result_uu['return_msg']) ? ':'.$result_uu['return_msg'] : '');
                }
            }
        } elseif ($platform === 7) {
            // 顺丰
            if (isset($send_platform_data[7])) {
                return $this->error('顺丰已经发过配送单了');
            } elseif (!$send_shop->shop_id_sf && !in_array(7, $shipper_platform_data)) {
                return $this->error('门店未开通顺丰跑腿');
            } elseif (!$sf_switch) {
                return $this->error('门店关闭顺丰跑腿');
            } else {
                $zy_sf = in_array(7, $shipper_platform_data);
                if ($zy_sf) {
                    // 自有顺丰
                    $shunfeng = app("shunfengservice");
                    $sf_add_money = 0;
                } else {
                    // 聚合顺丰
                    $shunfeng = app("shunfeng");
                    $sf_add_money = $add_money;
                }
                $result_sf = $shunfeng->createOrder($order, $shop);
                if (isset($result_sf['error_code']) && $result_sf['error_code'] == 0 && !empty($result_sf['result'])) {
                    $sf_money = sprintf("%.2f", ($result_sf['result']['real_pay_money'] / 100) + $sf_add_money);
                    $update_info = [
                        'money_sf' => $sf_money,
                        'shipper_type_sf' => $zy_sf ? 1 : 0,
                        'sf_order_id' => $result_sf['result']['sf_order_id'] ?? $order->order_id,
                        'sf_status' => 20,
                        'status' => 20,
                        'push_at' => date("Y-m-d H:i:s")
                    ];
                    DB::table('orders')->where('id', $order->id)->update($update_info);
                    DB::table('order_logs')->insert([
                        'ps' => 7,
                        'order_id' => $order->id,
                        'des' => '「顺丰」跑腿发单',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    try {
                        DB::transaction(function () use ($order, $zy_sf, $result_sf, $sf_add_money, $sf_money) {
                            $delivery_id = DB::table('order_deliveries')->insertGetId([
                                'user_id' => $order->user_id,
                                'shop_id' => $order->shop_id,
                                'warehouse_id' => $order->warehouse_id,
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'order_no' => $order->order_id,
                                'three_order_no' => $result_sf['result']['sf_order_id'] ?? '',
                                'platform' => 7,
                                'type' => $zy_sf ? 1 : 0,
                                'day_seq' => $order->day_seq,
                                'money' => $sf_money,
                                'add_money' => $zy_sf ? $sf_add_money : 0,
                                'original' => ($result_sf['result']['total_pay_money'] ?? 0) / 100,
                                'coupon' => ($result_sf['result']['coupons_total_fee'] ?? 0) / 100,
                                'distance' => $result_sf['result']['delivery_distance_meter'] ?? 0,
                                'weight' => ($result_sf['result']['weight_gram'] ?? 0) / 1000,
                                'status' => 20,
                                'track' => '待接单',
                                'send_at' => date("Y-m-d H:i:s"),
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                            DB::table('order_delivery_tracks')->insert([
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'delivery_id' => $delivery_id,
                                'status' => 20,
                                'status_des' => '下单成功',
                                'description' => '顺丰单号：' . $result_sf['result']['sf_order_id'] ?? '',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                        });
                    } catch (\Exception $exception) {
                        Log::info("顺丰写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    }
                    return $this->success('顺丰发单成功');
                } else {
                    return $this->error('顺丰发单失败' . !empty($result_sf['msg']) ? ':'.$result_sf['msg'] : '');
                }
            }
        } elseif ($platform === 8) {
            // 美团众包
            if (isset($send_platform_data[8])) {
                return $this->error('美团众包已经发过配送单了');
            } elseif (!$send_shop->shop_id_zb) {
                return $this->error('门店未开通美团众包');
            } elseif (!in_array($shop->meituan_bind_platform, [4, 31])) {
                return $this->error('门店未绑定民康、闪购');
            } elseif ($order->shop_id != $send_shop->id) {
                return $this->error('仓库发货订单，不支持美团众包派单');
            } elseif (!$zb_switch) {
                return $this->error('门店关闭美团众包');
            } else {
                if ($shop->meituan_bind_platform == 4) {
                    $meituan_shop_id = '';
                    $zhongbaoapp = app('minkang');
                } elseif ($shop->meituan_bind_platform == 31) {
                    $meituan_shop_id = $shop->waimai_mt;
                    $zhongbaoapp = app('meiquan');
                }
                $check_zb= $zhongbaoapp->zhongBaoShippingFee($order->order_id, $meituan_shop_id);
                if (!isset($check_zb['data'][0]['shipping_fee'])) {
                    return $this->error('众包发单失败');
                }
                // 计算配送费返回众包金额
                $zb_money = $check_zb['data'][0]['shipping_fee'];
                $result_zb = $zhongbaoapp->zhongBaoDispatch($order->order_id, $zb_money, $meituan_shop_id);
                if ($result_zb['data'] === 'ok') {
                    // 写入订单信息
                    $update_info = [
                        'zb_status' => 20,
                        'status' => 20,
                        'push_at' => date("Y-m-d H:i:s")
                    ];
                    DB::table('orders')->where('id', $order->id)->update($update_info);
                    DB::table('order_logs')->insert([
                        'ps' => 8,
                        'order_id' => $order->id,
                        'des' => '「美团众包」跑腿发单',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    try {
                        DB::transaction(function () use ($order, $zb_money) {
                            $delivery_id = DB::table('order_deliveries')->insertGetId([
                                'user_id' => $order->user_id,
                                'shop_id' => $order->shop_id,
                                'warehouse_id' => $order->warehouse_id,
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'order_no' => $order->order_id,
                                'three_order_no' => $order->order_id,
                                'platform' => 8,
                                'type' => 0,
                                'day_seq' => $order->day_seq,
                                'money' => $zb_money,
                                'original' => $zb_money,
                                'coupon' => 0,
                                'distance' => 0,
                                'weight' => 0,
                                'status' => 20,
                                'track' => '待接单',
                                'send_at' => date("Y-m-d H:i:s"),
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                            DB::table('order_delivery_tracks')->insert([
                                'order_id' => $order->id,
                                'wm_id' => $order->wm_id,
                                'delivery_id' => $delivery_id,
                                'status' => 20,
                                'status_des' => '下单成功',
                                'description' => '美团众包单号：' . $order->order_id,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ]);
                        });
                    } catch (\Exception $exception) {
                        Log::info("美团众包写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    }
                    return $this->success('美团众包发单成功');
                } else {
                    return $this->error('美团众包发单失败' . !empty($result_zb['msg']) ? ':'.$result_zb['msg'] : '');
                }
            }
        }
        return $this->error('平台选择错误');
    }

    /**
     * 添加小费
     * @data 2023/8/9 5:29 下午
     */
    public function add_tip(Request $request)
    {
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        // 如果订单状态是已接单状态，不发单
        if ($order->status !== 20) {
            return $this->error("当前订单状态不能加小费");
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        $_rand = rand(1, 2);
        if ($_rand === 1) {
            return $this->message('添加小费成功');
        } else {
            return $this->error('添加小费失败');
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        return $this->success();
    }

    /**
     * 打印订单
     * @data 2023/8/10 9:20 上午
     */
    public function print_order(Request $request)
    {
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        if (!$wm_order = WmOrder::find($order->wm_id)) {
            return $this->error("该订单不是外卖订单，不能打印小票");
        }

        if (!$print = WmPrinter::where('shop_id', $wm_order->shop_id)->first()) {
            return $this->error("该订单门店没有绑定打印机");
        }

        dispatch(new PrintWaiMaiOrder($order->id, $print));

        return $this->success();
    }

    /**
     * 订单日志
     * @data 2023/8/10 9:20 上午
     */
    public function operate_record(Request $request)
    {
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::select('id', 'shop_id')->find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }

        $res = [];
        $logs = OrderLog::where('order_id', $order->id)->get();
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $res[] = [
                    'description' => $log->des,
                    'user' => $log->name ? $log->name . '<br>' . $log->phone : '',
                    'created_at' => substr($log->created_at, 5, 11),
                ];
            }
        }

        return $this->success($res);
    }

    public function address_recognition(Request $request)
    {
        if (!$text = $request->get('text')) {
            return $this->error('请输入收货人信息');
        }
        preg_match_all('/\d{11}/', $text, $preg_result);
        if (empty($preg_result[0])) {
            return $this->error('识别信息不完整，请完善地址信息');
        }
        // if (!$shop_id = $request->get('shop_id', 0)) {
        //     return $this->error('请选择发货门店');
        // }
        // if (!$shop = Shop::select('id', 'user_id', 'running_select')->find($shop_id)) {
        //     return $this->error('门店不存在');
        // }
        // $user = $request->user();
        // if (!in_array($shop->id, $user->shops()->pluck('id')->toArray())) {
        //     return $this->error('门店不存在');
        // }
        $address_res = app(AddressRecognitionHandler::class)->run($text);
        $address_data = json_decode($address_res, true);
        if (empty($address_data['phonenum'])) {
            return $this->error('识别信息不完整，请完善地址信息');
        }
        $result = [
            'name' => $address_data['person'],
            'phone' => $address_data['phonenum'],
            'address' => $address_data['detail'],
            'province' => $address_data['province'],
            'city' => $address_data['city'],
            'county' => $address_data['county'],
            'city_code' => $address_data['city_code'],
        ];

        return $this->success($result);
    }

    public function map_search(Request $request)
    {
        if (!$address = $request->get('address')) {
            return $this->error('请输入收货人信息');
        }
        if (!$shop_id = $request->get('shop_id', 0)) {
            return $this->error('请选择发货门店');
        }
        if (!$shop = Shop::select('id', 'user_id', 'running_select')->find($shop_id)) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if (!in_array($shop->id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('门店不存在');
        }

        $data = amap_address_search($address, $shop->city, $shop->shop_lng, $shop->shop_lat);

        $result = [];
        if (!empty($data)) {
            foreach ($data as $v) {
                if (!empty($v['location'])) {
                    $location = explode(',', $v['location']);
                    $result[] = [
                        'district' => $v['district'] . ',' .$v['address'],
                        'address' => $v['address'],
                        'lng' => $location[0],
                        'lat' => $location[1],
                        'name' => $v['name'],
                    ];
                }
            }
        }

        return $this->success($result);
    }
}
