<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Events\OrderCancel;
use App\Handlers\AddressRecognitionHandler;
use App\Http\Controllers\Controller;
use App\Jobs\MtLogisticsSync;
use App\Jobs\PrintWaiMaiOrder;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\ShopRider;
use App\Models\UserMoneyBalance;
use App\Models\WmOrder;
use App\Models\WmPrinter;
use App\Traits\RiderOrderCancel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    use RiderOrderCancel;
    /**
     * 订单统计
     * @data 2023/8/7 10:38 下午
     */
    public function statistics(Request $request)
    {
        $shop_id = $request->get('shop_id', '');
        $order_where = [['ignore', '=', 0], ['created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day'))],];
        $wm_order_where = [['created_at', '>', date('Y-m-d H:i:s', strtotime('-1 day'))],];
        $finish_order_where = [['over_at', '>', date('Y-m-d')], ['status', '>=', 70], ['status', '<=', 75]];
        // $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
        // $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
        // // 判断权限
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            $order_where[] = [function ($query) use ($request) {
                $query->whereIn('shop_id', $request->user()->shops()->pluck('id')->toArray());
            }];
            $wm_order_where[] = [function ($query) use ($request) {
                $query->whereIn('shop_id', $request->user()->shops()->pluck('id')->toArray());
            }];
            $finish_order_where[] = [function ($query) use ($request) {
                $query->whereIn('shop_id', $request->user()->shops()->pluck('id')->toArray());
            }];
            // $order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
            // $wm_order_where[] = ['shop_id', 'in', $request->user()->shops()->pluck('id')->toArray()];
        }
        if ($shop_id) {
            $order_where[] = ['shop_id', '=', $shop_id];
            $wm_order_where[] = ['shop_id', '=', $shop_id];
            $finish_order_where[] = ['shop_id', '=', $shop_id];
        }
        $result = [
            'new' => Order::select('id')->where($order_where)->whereIn('status', [0, 3, 7, 8])->count(),
            'pending' => Order::select('id')->where($order_where)->where('status', 20)->count(),
            'receiving' => Order::select('id')->where($order_where)->where('status', 50)->count(),
            'delivering' => Order::select('id')->where($order_where)->where('status', 60)->count(),
            'exceptional' => Order::select('id')->where($order_where)->whereIn('status', [10, 5])->count(),
            'refund' => WmOrder::select('id')->where($wm_order_where)->where('status', 30)->count(),
            'remind' => Order::select('id')->where($order_where)->where('status', '>', 70)->where('remind_num', '>', 0)->count(),
            'finish' => Order::select('id')->where($finish_order_where)->count(),
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
        $source = (int) $request->get('source', 0);
        $order_by = $request->get('order', 0);
        if (!in_array($status, [10,20,30,40,50,60,70,80])) {
            $status = 10;
        }
        $query = Order::with(['products' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'spec', 'upc', 'quantity','price','image');
        }, 'deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type',
                'money', 'updated_at','delivery_name','delivery_phone');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'order' => function ($query) {
            $query->select('id', 'poi_receive','delivery_time', 'estimate_arrival_time', 'status');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_lng','shop_lat','shop_name');
        }])->select('id','order_id','wm_id','shop_id','wm_poi_name','receiver_name','receiver_phone','receiver_address','receiver_lng','receiver_lat',
            'caution','day_seq','platform','status','created_at', 'ps as logistic_type','push_at','receive_at','take_at','over_at','cancel_at',
            'courier_name', 'courier_phone','courier_lng','courier_lat','poi_receive','send_at','ps_type','cancel_at')
            ->where('ignore', 0)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-2 day')));
        // 判断权限
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
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
            $query->whereIn('status', [10, 5, 99]);
        } elseif ($status === 60) {
            // $query->where('status', 20);
            $query->whereHas('order', function ($query) {
                $query->where('status', 30);
            })->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-1 day')));;
        } elseif ($status === 70) {
            $query->where('status', '<', 70)->where('remind_num', '>', 0);
        } elseif ($status === 80) {
            $query->whereIn('status', [70, 75])->where('over_at', '>', date('Y-m-d'));
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
            $query->orderByDesc('poi_receive');
        } elseif ($order_by === 'receive_asc') {
            $query->orderBy('poi_receive');
        } elseif ($order_by === 'create_asc') {
            $query->orderBy('id');
        } else {
            if ($status === 50) {
                $query->orderByDesc('cancel_at');
            }
            $query->orderByDesc('id');
        }
        // 排序-结束
        $orders = $query->paginate($page_size);
        // 商品图片
        // $images = [];
        // if (!empty($orders)) {
        //     $upcs = [];
        //     foreach ($orders as $order) {
        //         if (!empty($order->products)) {
        //             foreach ($order->products as $product) {
        //                 if ($product->upc) {
        //                     $upcs[] = $product->upc;
        //                 }
        //             }
        //         }
        //     }
        //     if (!empty($upcs)) {
        //         $images = MedicineDepot::whereIn('upc', $upcs)->pluck('cover', 'upc');
        //     }
        // }
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $number = 0;
                if (!empty($order->send_at) && ($second = strtotime($order->send_at)) > 0) {
                    $number = $second - time() > 0 ? $second - time() : 0;
                }
                if ($order->status == 8 && $number == 0 ) {
                    $order->status = 0;
                }
                $order->number = $number;
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
                if (in_array($order->status, [20,50,60,70,99])) {
                    $order->status_title = OrderDelivery::$delivery_status_order_list_title_map[$order->status] ?? '其它';
                    if ($order->status === 20) {
                        $order->status_description = '下单成功';
                    } else {
                        if ($order->ps_type == 2) {
                            $order->status_description = "[未知配送]";
                        } else {
                            $status_description_platform = OrderDelivery::$delivery_platform_map[$order->logistic_type];
                            $order->status_description = "[{$status_description_platform}] {$order->courier_name} {$order->courier_phone}";
                        }
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
                        // if ($product->upc) {
                        //     $product->image = $images[$product->upc] ?? '';
                        // }
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
                $order->locations = $locations;
                unset($order->shop);
                // 跑腿配送平台
                $order->logistic_tag = '';
                if ($order->ps_type > 0) {
                    if ($order->ps_type == 1) {
                        $order->logistic_tag = '平台配送';
                    } elseif ($order->ps_type == 2) {
                        $order->logistic_tag = '未知配送';
                    }
                }
                unset($order->ps_type);
            }
        }
        return $this->page($orders);
    }

    /**
     * 搜索订单列表
     * @data 2023/8/7 10:39 下午
     */
    public function search_list(Request $request)
    {
        // 10 今日，20 昨日，30 本周，40 本月，50 上月，80 指定日期
        $date_type = (int) $request->get('date_type', '');
        // 10 流水号，20 顾客手机号，30 配送员手机号，40 订单编号
        $search_type = (int) $request->get('search_type', '');
        // 搜索关键字、搜索日期
        $search_key = $request->get('search_key', '');
        $date_range = $request->get('date_range', '');
        $shop_id = $request->get('shop_id', 0);
        // 订单类型（0 全部，10 已完成，20 即时单，30 预约单，40 未配送，50 已取消）
        $order_type = (int) $request->get('order_type', 0);
        // 订单标签（0 全部，10 配送超时， 20 顾客自提， 30 忽略配送）
        $order_tag = (int) $request->get('order_tag', 0);
        // 订单来源（0 全部，1 美团外卖，2 饿了么，10 其它）
        $order_source = (int) $request->get('order_source', 0);

        // 没有搜索关键字、搜索日期类型，返回空
        if (empty($search_type) && empty($date_type)) {
            return $this->success();
        }
        $user_shop_ids = $request->user()->shops()->pluck('id')->toArray();
        // 门店判断
        if ($shop_id) {
            if (!in_array($shop_id, $user_shop_ids)) {
                return $this->error('门店不存在');
            }
        }
        // 关键字搜索判断
        if ($search_type) {
            if (!in_array($search_type, [10,20,30,40])) {
                return $this->error('搜索类型错误');
            }
            if (!$search_key) {
                return $this->error('搜索关键字不能为空');
            }
        }
        // 日期搜索判断
        $start_date = '';
        $end_date = '';
        if ($date_type) {
            if (!in_array($date_type, [10, 20, 30, 40, 50, 80])) {
                return $this->error('日期类型错误');
            }
            if ($date_type === 10) {
                $start_date = date("Y-m-d");
                $end_date = date("Y-m-d");
            } elseif ($date_type === 20) {
                $start_date = date('Y-m-d', strtotime('-1 day'));
                $end_date = date('Y-m-d', strtotime('-1 day'));
            } elseif ($date_type === 30) {
                $start_date = date('Y-m-d', strtotime('this week Monday'));
                $end_date = date('Y-m-d', strtotime('this week Sunday'));
            } elseif ($date_type === 40) {
                $start_date = date("Y-m-01");
                $end_date = date("Y-m-d", strtotime("$start_date +1 month -1 day"));
            } elseif ($date_type === 40) {
                $start_date = date("Y-m-01");
                $end_date = date("Y-m-t");
            } elseif ($date_type === 50) {
                $start_date = date("Y-m-01", strtotime('-1 month'));
                $end_date = date("Y-m-t", strtotime('-1 month'));
            } elseif ($date_type === 80) {
                if (!$date_range) {
                    return $this->error('日期范围不能为空');
                }
                $date_arr = explode(',', $date_range);
                if (count($date_arr) !== 2) {
                    return $this->error('日期格式不正确');
                }
                $start_date = $date_arr[0];
                $end_date = $date_arr[1];
                if ($start_date !== date("Y-m-d", strtotime($start_date))) {
                    return $this->error('日期格式不正确');
                }
                if ($end_date !== date("Y-m-d", strtotime($end_date))) {
                    return $this->error('日期格式不正确');
                }
                if ((strtotime($end_date) - strtotime($start_date)) / 86400 > 31) {
                    return $this->error('时间范围不能超过31天');
                }
            }
        }

        $page_size = $request->get('page_size', 10);
        $query = Order::with(['products' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'spec', 'upc', 'quantity','price', 'image');
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
            'courier_name', 'courier_phone','ps_type');
        // 判断权限
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id')->toArray());
        }
        if ($search_type === 10) {
            $query->where('day_seq', $search_key);
        } elseif ($search_type === 40) {
            $query->where('order_id', $search_key);
        }
        if ($start_date && $end_date) {
            $query->where('created_at', '>', $start_date)
                ->where('created_at', '<', date("Y-m-d", strtotime($end_date) + 86400));
        }
        if ($order_type) {
            // 订单类型（0 全部，10 已完成，20 即时单，30 预约单，40 未配送，50 已取消）
            if ($order_type === 10) {
                $query->whereIn('status', [70, 75]);
            } elseif ($order_type === 20) {
                $query->where('expected_delivery_time', 0);
            } elseif ($order_type === 30) {
                $query->where('expected_delivery_time', '>', 0);
            } elseif ($order_type === 40) {
                $query->where('status', '<', 10);
            } elseif ($order_type === 50) {
                $query->where('status', 99);
            }
        }
        if ($order_tag) {
            // 订单标签（0 全部，10 配送超时， 20 顾客自提， 30 忽略配送）
            if ($order_tag === 20) {
                $query->where('pick_type', 1);
                // } elseif ($order_tag === 10) {
                //     $query->where('expected_delivery_time', 0);
            } elseif ($order_tag === 30) {
                $query->where('ignore', 1);
            }
        }
        if ($order_source) {
            // 订单来源（0 全部，1 美团外卖，2 饿了么，10 其它）
            if ($order_source === 1) {
                $query->where('platform', 1);
            } elseif ($order_source === 2) {
                $query->where('platform', 2);
            } elseif ($order_source === 10) {
                // 其它 （0 手动创建， 11 药柜）
                $query->whereIn('platform', [0, 11]);
            }
        }
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        // } else {
        //     $query->whereIn('shop_id', $user_shop_ids);
        }
        // 查询订单
        $orders = $query->orderByDesc('id')->paginate($page_size);
        // 商品图片
        // $images = [];
        // if (!empty($orders)) {
        //     $upcs = [];
        //     foreach ($orders as $order) {
        //         if (!empty($order->products)) {
        //             foreach ($order->products as $product) {
        //                 if ($product->upc) {
        //                     $upcs[] = $product->upc;
        //                 }
        //             }
        //         }
        //     }
        //     if (!empty($upcs)) {
        //         $images = MedicineDepot::whereIn('upc', $upcs)->pluck('cover', 'upc');
        //     }
        // }
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
                if (in_array($order->status, [20,50,60,70,99])) {
                    $order->status_title = OrderDelivery::$delivery_status_order_list_title_map[$order->status] ?? '其它';
                    if ($order->status === 20) {
                        $order->status_description = '下单成功';
                    } else {
                        if ($order->ps_type == 2) {
                            $order->status_description = "[未知配送]";
                        } else {
                            $status_description_platform = OrderDelivery::$delivery_platform_map[$order->logistic_type];
                            $order->status_description = "[{$status_description_platform}] {$order->courier_name} {$order->courier_phone}";
                        }
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
                        // if ($product->upc) {
                        //     $product->image = $images[$product->upc] ?? '';
                        // }
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
                // 跑腿配送平台
                $order->logistic_tag = '';
                if ($order->ps_type > 0) {
                    if ($order->ps_type == 1) {
                        $order->logistic_tag = '平台配送';
                    } elseif ($order->ps_type == 2) {
                        $order->logistic_tag = '未知配送';
                    }
                }
                unset($order->ps_type);
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
            'courier_name', 'courier_phone','courier_lng','courier_lat','money as shipping_fee','send_at','ps_type')
            ->find(intval($request->get('id', 0)))) {
            return $this->error('订单不存在');
        }
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        $order->load(['products' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'spec', 'upc', 'quantity','price','image');
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

        // 倒计时
        $number = 0;
        if (!empty($order->send_at) && ($second = strtotime($order->send_at)) > 0) {
            $number = $second - time() > 0 ? $second - time() : 0;
        }
        if ($order->status == 8 && $number == 0 ) {
            $order->status = 0;
        }
        $order->number = $number;
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
        // $images = [];
        // if (!empty($order->products)) {
        //     foreach ($order->products as $product) {
        //         if ($product->upc) {
        //             $upcs[] = $product->upc;
        //         }
        //     }
        // }
        // if (!empty($upcs)) {
        //     $images = MedicineDepot::whereIn('upc', $upcs)->pluck('cover', 'upc');
        // }
        // 商品信息
        if (!empty($order->products)) {
            $product_num = 0;
            foreach ($order->products as $product) {
                $product_num += $product->quantity;
                // if ($product->upc) {
                //     $product->image = $images[$product->upc] ?? '';
                // }
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
        if ($order->status <= 20 || $order->ps_type > 0) {
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
        // 跑腿配送平台
        $order->logistic_tag = '';
        if ($order->ps_type > 0) {
            if ($order->ps_type == 1) {
                $order->logistic_tag = '平台配送';
            } elseif ($order->ps_type == 2) {
                $order->logistic_tag = '未知配送';
            }
        }
        unset($order->ps_type);

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
        // ------
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
        if (!$order = Order::select('id', 'shop_id', 'ignore', 'wm_id')->find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        if ($order->ignore == 0) {
            $order->ignore = 1;
            $order->save();
        }
        if ($order->wm_id) {
            WmOrder::where('id', $order->wm_id)->update(['ignore' => 1]);
        }
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
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        \Log::info("[跑腿订单-用户操作取消订单APP]-[订单号: {$order->order_id}]-开始");
        $ps = $order->ps;

        if ($order->status == 99) {
            // 已经是取消状态
            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-已经是取消状态");
            return $this->success();
        } elseif ($order->status == 80) {
            // 异常状态
            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-异常状态");
            return $this->success();
        } elseif ($order->status == 70) {
            // 已经完成
            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-订单已经完成，不能取消");
            return $this->error("订单已经完成，不能取消");
        } elseif ($order->ps == 200) {
            // 自配送取消
            $order->status = 99;
            $order->cancel_at = date("Y-m-d H:i:s");
            $order->save();
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "用户操作取消[自配送]订单"
            ]);
            // 跑腿运力取消
            OrderDelivery::cancel_log($order->id, 200, 'APP操作');
            event(new OrderCancel($order->id, 4));
            return $this->success();
        } elseif (in_array($order->status, [40, 50, 60])) {
            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-已有平台接单，订单状态：{$order->status}");
            $dd = app("ding");
            if ($ps == 1) {
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] === 0) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->take_at)) {
                                $jian_money = $order->money;
                            }
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "用户操作取消美团跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            if ($jian_money > 0) {
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "用户操作取消美团跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                            }
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mt_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[美团跑腿]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消美团订单返回失败",
                        "id" => $order->id,
                        "ps" => "美团",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消美团订单返回失败", $logs);
                }
            } elseif ($ps == 2) {
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] == 200) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->receive_at)) {
                                $jian = time() - strtotime($order->receive_at);
                                if ($jian <= 1200) {
                                    $jian_money = 2;
                                }
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                            }
                            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-扣款：{$jian_money}");
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "（用户操作）取消蜂鸟跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            if ($jian_money > 0) {
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "（用户操作）取消蜂鸟跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                            }
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'fn_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[蜂鸟跑腿]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "蜂鸟",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消蜂鸟订单返回失败", $logs);
                }
            } elseif ($ps == 3) {
                if ($order->shipper_type_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                } else {
                    $shansong = app("shansong");
                }
                $result = $shansong->cancelOrder($order->ss_order_id);
                if (($result['status'] == 200) || ($result['msg'] = '订单已经取消')) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 3, '中台操作');
                    try {
                        DB::transaction(function () use ($order, $result) {
                            if ($order->shipper_type_ss == 0) {
                                // 计算扣款
                                $jian_money = 0;
                                if (isset($result['data']['deductAmount']) && is_numeric($result['data']['deductAmount'])) {
                                    $jian_money = $result['data']['deductAmount'] / 100;
                                    \Log::info("主动取消闪送订单，返款扣款金额：" . $jian_money);
                                } else {
                                    if (!empty($order->receive_at)) {
                                        $jian_money = 2;
                                        $jian = time() - strtotime($order->receive_at);
                                        if ($jian >= 480) {
                                            $jian_money = 5;
                                        }
                                        if (!empty($order->take_at)) {
                                            $jian_money = 5;
                                        }
                                    }
                                }
                                // if (!empty($order->receive_at)) {
                                //     $jian_money = 2;
                                //     $jian = time() - strtotime($order->receive_at);
                                //     if ($jian >= 480) {
                                //         $jian_money = 5;
                                //     }
                                //     if (!empty($order->take_at)) {
                                //         $jian_money = 5;
                                //     }
                                // }

                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "用户操作取消闪送跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "用户操作取消闪送跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-自主注册闪送，取消不扣款");
                            }
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'ss_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[闪送跑腿]订单"
                            ]);
                            event(new OrderCancel($order->id, 3));
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消闪送订单返回失败",
                        "id" => $order->id,
                        "ps" => "闪送",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消闪送订单返回失败", $logs);
                }
            } elseif ($ps == 4) {
                $fengniao = app("meiquanda");
                $result = $fengniao->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "用户操作取消美全达跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mqd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            DB::table('users')->where('id', $order->user_id)->increment('money', $order->money);
                            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[美全达跑腿]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消美全达订单返回失败",
                        "id" => $order->id,
                        "ps" => "美全达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消美全达订单返回失败", $logs);
                }
            } elseif ($ps == 5) {
                if ($order->shipper_type_dd) {
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    $dada = app("dada");
                }
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 5, '中台操作');
                    try {
                        DB::transaction(function () use ($order) {
                            if ($order->shipper_type_dd == 0) {
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->receive_at)) {
                                    $jian = time() - strtotime($order->receive_at);
                                    if ($jian >= 60 && $jian <= 900) {
                                        $jian_money = 2;
                                    }
                                }
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "用户操作取消达达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "用户操作取消达达跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-自主注册，不扣款");
                            }
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'dd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[达达跑腿]订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消达达订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消达达订单返回失败",
                        "id" => $order->id,
                        "ps" => "达达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消达达订单返回失败", $logs);
                }
            } elseif ($ps == 6) {
                // 取消UU跑腿订单
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 6, '中台操作');
                    try {
                        DB::transaction(function () use ($order) {
                            // 用户余额日志
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->take_at)) {
                                $jian_money = 3;
                            }
                            // 当前用户
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "用户操作取消UU跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            UserMoneyBalance::create([
                                "user_id" => $order->user_id,
                                "money" => $jian_money,
                                "type" => 2,
                                "before_money" => ($current_user->money + $order->money),
                                "after_money" => ($current_user->money + $order->money - $jian_money),
                                "description" => "用户操作取消UU跑腿订单扣款：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'uu_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[UU跑腿]订单"
                            ]);
                            event(new OrderCancel($order->id, 6));
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "UU",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:UU]-取消UU订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消UU订单返回失败",
                        "id" => $order->id,
                        "ps" => "UU",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消UU订单返回失败", $logs);
                }
            } elseif ($ps == 7) {
                // 取消顺丰跑腿订单
                if ($order->shipper_type_sf) {
                    $sf = app("shunfengservice");
                } else {
                    $sf = app("shunfeng");
                }
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0 || $result['error_msg'] == '订单已取消, 不可以重复取消') {
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 7, '中台操作');
                    try {
                        DB::transaction(function () use ($order, $result) {
                            // 用户余额日志
                            if ($order->shipper_type_sf == 0) {
                                // 计算扣款
                                $jian_money = isset($result['result']['deduction_detail']['deduction_fee']) ? ($result['result']['deduction_detail']['deduction_fee']/100) : 0;
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-扣款金额：{$jian_money}");
                                // 当前用户
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "用户操作取消顺丰跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "用户操作取消顺丰跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-自主注册闪送，取消不扣款");
                            }
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'sf_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "用户操作取消[顺丰跑腿]订单"
                            ]);
                            event(new OrderCancel($order->id, 7));
                            // 顺丰跑腿运力
                            $sf_delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                            // 写入顺丰取消足迹
                            if ($sf_delivery) {
                                try {
                                    $sf_delivery->update([
                                        'status' => 99,
                                        'cancel_at' => date("Y-m-d H:i:s"),
                                        'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                    ]);
                                    OrderDeliveryTrack::firstOrCreate(
                                        [
                                            'delivery_id' => $sf_delivery->id,
                                            'status' => 99,
                                            'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                        ], [
                                            'order_id' => $sf_delivery->order_id,
                                            'wm_id' => $sf_delivery->wm_id,
                                            'delivery_id' => $sf_delivery->id,
                                            'status' => 99,
                                            'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                                        ]
                                    );
                                } catch (\Exception $exception) {
                                    Log::info("聚合闪送取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                                    $this->ding_error("聚合闪送取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                                }
                            }
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "[用户操作取消订单]更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "顺丰",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("用户操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-取消顺丰订单返回失败", [$result]);
                    $logs = [
                        "des" => "[用户操作取消订单]取消顺丰订单返回失败",
                        "id" => $order->id,
                        "ps" => "顺丰",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("用户操作取消订单，取消顺丰订单返回失败", $logs);
                }
            } elseif ($ps == 8) {
                $this->cancelRiderOrderMeiTuanZhongBao($order, 1, $request->user()->id);
            }
            return $this->success();
        } elseif (in_array($order->status, [20, 30])) {
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，订单状态：{$order->status}");
            // 没有骑手接单，取消订单
            if (in_array($order->mt_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消美团");
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->mt_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[美团跑腿]订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美团成功");
                }
            }
            if (in_array($order->fn_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消蜂鸟");
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] == 200) {
                    $order->status = 99;
                    $order->fn_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[蜂鸟跑腿]订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，蜂鸟成功");
                }
            }
            if (in_array($order->ss_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消闪送");
                if ($order->shipper_type_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                } else {
                    $shansong = app("shansong");
                }
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    $order->status = 99;
                    $order->ss_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[闪送跑腿]订单"
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 3, '中台操作');
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，闪送成功");
                }
            }
            if (in_array($order->mqd_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消美全达");
                $meiquanda = app("meiquanda");
                $result = $meiquanda->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    $order->status = 99;
                    $order->mqd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[美全达跑腿]订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美全达成功");
                }
            }
            if (in_array($order->dd_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消达达");
                if ($order->shipper_type_dd) {
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    $dada = app("dada");
                }
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->dd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[达达跑腿]订单"
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 5, '中台操作');
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，达达成功");
                }
            }
            if (in_array($order->uu_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消UU");
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    $order->status = 99;
                    $order->uu_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[UU跑腿]订单"
                    ]);
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 6, '中台操作');
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，UU成功");
                }
            }
            if (in_array($order->sf_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消顺丰");
                if ($order->shipper_type_sf) {
                    $sf = app("shunfengservice");
                } else {
                    $sf = app("shunfeng");
                }
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0 || $result['error_msg'] == '订单已取消, 不可以重复取消') {
                    $order->status = 99;
                    $order->sf_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "用户操作取消[顺丰跑腿]订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，顺丰成功");
                    // 跑腿运力取消
                    OrderDelivery::cancel_log($order->id, 7, '中台操作');
                    // // 顺丰跑腿运力
                    // $sf_delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                    // // 写入顺丰取消足迹
                    // if ($sf_delivery) {
                    //     try {
                    //         $sf_delivery->update([
                    //             'status' => 99,
                    //             'cancel_at' => date("Y-m-d H:i:s"),
                    //             'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    //         ]);
                    //         OrderDeliveryTrack::firstOrCreate(
                    //             [
                    //                 'delivery_id' => $sf_delivery->id,
                    //                 'status' => 99,
                    //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    //             ], [
                    //                 'order_id' => $sf_delivery->order_id,
                    //                 'wm_id' => $sf_delivery->wm_id,
                    //                 'delivery_id' => $sf_delivery->id,
                    //                 'status' => 99,
                    //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    //             ]
                    //         );
                    //     } catch (\Exception $exception) {
                    //         Log::info("聚合闪送取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                    //         $this->ding_error("聚合闪送取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    //     }
                    // }
                }
            }
            if (in_array($order->zb_status, [20, 30])) {
                $this->cancelRiderOrderMeiTuanZhongBao($order, 1, $request->user()->id);
            }
            return $this->success();
        } else {
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-状态小于20，属于未发单，直接操作取消，状态：{$order->status}");
            // 状态小于20，属于未发单，直接操作取消
            if ($order->status < 0) {
                \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-[订单状态：{$order->status}]-订单状态小于0");
                $order->status = -10;
            } else {
                $order->status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
            }
            $order->save();
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "操作取消跑腿订单"
            ]);
            \Log::info("[跑腿订单-用户操作取消订单]-[订单号: {$order->order_id}]-未配送");
            return $this->success();
        }

        return $this->error("取消失败");
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
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
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
            if (isset($check_zb['data']) && !empty($check_zb['data']) && is_array($check_zb['data'])) {
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
        $result['zps'] = [
            'platform' => 200,
            'platform_name' => '自配送',
            'price' => '',
            'distance' => '',
            'description' => '',
            'error_status' => 0,
            'error_msg' => '',
            'status' => 1,
            'checked' => 0,
            'tag' => '',
            'name' => $shop->contact_name,
            'phone' => $shop->contact_phone,
        ];
        if (!empty($wm_order->delivery_time)) {
            $order_title = '<text class="time-text" style="color: #5ac725">预约订单，' . date("m-d H:i", $order->order->delivery_time) . '</text>送达';
        } else {
            $order_title = '<text class="time-text" style="color: #5ac725">立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
        $res_data = [
            'id' => $order->id,
            'order_title' => $order_title,
            'logistic_tag' => '',
            'shop_id' => $order->shop_id,
            'day_seq' => $order->day_seq,
            'platform' => $order->platform,
            'wm_poi_name' => $order->wm_poi_name ?: $shop->shop_name,
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
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        // 获取门店
        if (!$shop = Shop::find($order->shop_id)) {
            return $this->error("门店不存在");
        }
        // 自配送参数
        $name = $request->get('name');
        $phone = $request->get('phone');
        // 记录发单操作人
        Log::info("{配送发单:$order->order_id}|操作人：{$request->user()->id}|平台:{$platform}");
        // 自配送发单
        if ($platform === 200) {
            if ($order->status == 20) {
                return $this->error('订单已发单，不能自配送，请先取消');
            } elseif ($order->status == 50) {
                return $this->error('订单已接单，不能自配送');
            } elseif ($order->status == 60) {
                return $this->error('订单已接单，不能自配送');
            } elseif ($order->status == 70) {
                return $this->error('订单已完成，不能自配送');
            } elseif ($order->status == 75) {
                return $this->error('订单已完成，不能自配送');
            } elseif ($order->status == 8) {
                return $this->error('订单即将发单，不能自配送，请先取消');
            }
            $update_info = [
                'money' => 0,
                'pay_status' => 1,
                'profit' => 0,
                'status' => 60,
                'ps' => 200,
                'courier_lng' => $shop->shop_lng,
                'courier_lat' => $shop->shop_lat,
                'courier_name' => $name,
                'courier_phone' => $phone,
                'push_at' => date("Y-m-d H:i:s"),
                'receive_at' => date("Y-m-d H:i:s"),
                'take_at' => date("Y-m-d H:i:s"),
                'pay_at' => date("Y-m-d H:i:s"),
            ];
            if (Order::where('id', $order->id)->whereNotIn('status', [8,20,50,60,70,75])->update($update_info)) {
                DB::table('order_logs')->insert([
                    'ps' => 200,
                    'order_id' => $order->id,
                    'des' => '「自配送」发单',
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                ]);
                try {
                    DB::transaction(function () use ($order, $name, $phone) {
                        ShopRider::firstOrCreate(
                            [
                                'shop_id' => $order->shop_id,
                                'name' => $name,
                                'phone' => $phone,
                            ], [
                                'shop_id' => $order->shop_id,
                                'name' => $name,
                                'phone' => $phone,
                            ]
                        );
                        $delivery_id = DB::table('order_deliveries')->insertGetId([
                            'user_id' => $order->user_id,
                            'shop_id' => $order->shop_id,
                            'warehouse_id' => $order->warehouse_id,
                            'order_id' => $order->id,
                            'wm_id' => $order->wm_id,
                            'order_no' => $order->order_id,
                            'three_order_no' => '',
                            'platform' => 200,
                            'type' => 0,
                            'day_seq' => $order->day_seq,
                            'money' => 0,
                            'status' => 60,
                            'send_at' => date("Y-m-d H:i:s"),
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            'atshop_at' => date("Y-m-d H:i:s"),
                            'pickup_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_DELIVERING,
                        ]);
                        DB::table('order_delivery_tracks')->insert([
                            'order_id' => $order->id,
                            'wm_id' => $order->wm_id,
                            'delivery_id' => $delivery_id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'status' => 60,
                            'status_des' => OrderDeliveryTrack::TRACK_STATUS_DELIVERING,
                            'delivery_name' => $name,
                            'delivery_phone' => $phone,
                            'delivery_lng' => $locations['lng'] ?? '',
                            'delivery_lat' => $locations['lat'] ?? '',
                            // 'description' => OrderDeliveryTrack::TRACK_DESCRIPTION_DELIVERING,
                            'description' => "配送员: {$name} <br>联系方式：{$phone}",
                        ]);
                    });
                } catch (\Exception $exception) {
                    Log::info("自配送写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                }
                dispatch(new MtLogisticsSync($order));
            } else {
                return $this->error('该订单状态不能发起自配送');
            }
            return $this->success();
        }
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
                if (empty($check_ss['data']['orderNumber'])) {
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
                if (empty($check_dd['result']['deliveryNo'])) {
                    return $this->error('达达发单失败' . !empty($check_dd['msg']) ? ':'.$check_dd['msg'] : '');
                }
                // 计算配送费返回达达订单号
                $dada_order_id = $check_dd['result']['deliveryNo'];
                $result_dd = $dada->createOrder($dada_order_id);
                if (isset($result_dd['code']) && $result_dd['code'] == 0) {
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
                if (empty($check_uu['price_token'])) {
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
     * 1. 闪送不支持小数，将数值向下取整，添加小费
     */
    public function add_tip(Request $request)
    {
        // 判断小费金额
        $tip = (float) $request->get('tip');
        if (!is_numeric($tip)) {
            return $this->error("小费金额格式不正确");
        }
        if (!is_int($tip)) {
            $tip = (floor($tip * 10) / 10);
        }
        if ($tip <= 0) {
            return $this->error("小费金额不能小于等于0");
        }
        // 判断平台
        $platform = (int) $request->get('platform');
        if (!in_array($platform, [0,3,5,6,7,8])) {
            return $this->error("平台不正确");
        }
        // 判断订单
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
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
        // $_rand = rand(1, 2);
        // if ($_rand === 1) {
        //     return $this->message('添加小费成功');
        // } else {
        //     return $this->error('添加小费失败');
        // }
        // ---------------------------------------------------------------------------------------------------------
        // ---------------------------------------------------------------------------------------------------------
        $deliveries = OrderDelivery::where('order_id', $order->id)->where('status', 20)->get();
        if ($deliveries->isEmpty()) {
            return $this->error("没有平台可以加小费");
        }
        $message_data = [];
        foreach ($deliveries as $delivery) {
            // 闪送加小费
            if (floor($tip) >= 1) {
                $ss_tip = floor($tip);
                if ($delivery->platform === 3 && ($platform === 3 || $platform === 0)) {
                    if ($delivery->type == 1) {
                        // 自有闪送
                        $shansong = new ShanSongService(config('ps.shansongservice'));
                    } else {
                        // 聚合闪送
                        $shansong = app("shansong");
                    }
                    $ss_res = $shansong->add_tip($delivery->three_order_no, $ss_tip);
                    if (isset($ss_res['status']) && $ss_res['status'] == 200) {
                        Order::where('id', $order->id)->increment('money_ss', $ss_tip);
                        OrderDelivery::where('id', $delivery->id)->increment('money', $ss_tip);
                        OrderDelivery::where('id', $delivery->id)->increment('tip', $ss_tip);
                        $message_data[] = "闪送加{$ss_tip}元小费成功";
                    } else {
                        $message_data[] = "闪送失败:" . $ss_res['msg'] ?? '系统错误';
                    }
                }
            } else {
                $message_data[] = "闪送失败:小费金额不能低于1元";
            }
            // 达达加小费
            if ($delivery->platform === 5 && ($platform === 5 || $platform === 0)) {
                if ($delivery->type == 1) {
                    // 自有达达
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    // 聚合达达
                    $dada = app("dada");
                }
                $dd_res = $dada->add_tip($delivery->order_no, $delivery->tip + $tip);
                if (isset($dd_res['code']) && $dd_res['code'] == 0) {
                    Order::where('id', $order->id)->increment('money_dd', $tip);
                    OrderDelivery::where('id', $delivery->id)->increment('money', $tip);
                    OrderDelivery::where('id', $delivery->id)->increment('tip', $tip);
                    $message_data[] = "达达加{$tip}元小费成功";
                } else {
                    $message_data[] = "达达失败:" . $dd_res['msg'] ?? '系统错误';
                }
            }
            // UU加小费
            if (floor($tip) >= 1) {
                $uu_tip = floor($tip);
                if ($delivery->platform === 6 && ($platform === 6 || $platform === 0)) {
                    $dada = app("uu");
                    $uu_res = $dada->add_tip($delivery->three_order_no, $delivery->order_no, $uu_tip);
                    if (isset($uu_res['return_code']) && $uu_res['return_code'] == 'ok') {
                        Order::where('id', $order->id)->increment('money_uu', $uu_tip);
                        OrderDelivery::where('id', $delivery->id)->increment('money', $uu_tip);
                        OrderDelivery::where('id', $delivery->id)->increment('tip', $uu_tip);
                        $message_data[] = "UU加{$uu_tip}元小费成功";
                    } else {
                        $message_data[] = "UU失败:" . $uu_res['return_msg'] ?? '系统错误';
                    }
                }
            }
            // 顺丰加小费
            if ($delivery->platform === 7 && ($platform === 7 || $platform === 0)) {
                if ($delivery->type == 1) {
                    // 自有顺丰
                    $shunfeng = app("shunfengservice");
                } else {
                    // 聚合顺丰
                    $shunfeng = app("shunfeng");
                }
                $shop_id = $order->warehouse_id ?: $order->shop_id;
                if ($delivery->type == 0) {
                    $shop = Shop::select('id', 'citycode')->find($shop_id);
                    $shop_id = intval($shop->citycode);
                }
                $sf_res = $shunfeng->add_tip($tip, $delivery->order_no, $shop_id);
                if (isset($sf_res['error_code']) && $sf_res['error_code'] == 0) {
                    Order::where('id', $order->id)->increment('money_sf', $tip);
                    OrderDelivery::where('id', $delivery->id)->increment('money', $tip);
                    OrderDelivery::where('id', $delivery->id)->increment('tip', $tip);
                    $message_data[] = "顺丰加{$tip}元小费成功";
                } else {
                    $message_data[] = "顺丰失败:" . $sf_res['error_msg'] ?? '系统错误';
                }
            }
        }
        if (count($message_data) > 1) {
            $res_message = implode(',', $message_data);
        } else {
            $res_message = $message_data[0] ?? '添加失败';
        }
        return $this->message($res_message);
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
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
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
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
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

    /**
     * 地址解析
     * @data 2023/8/11 3:29 下午
     */
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
            // 'province' => $address_data['province'],
            // 'city' => $address_data['city'],
            // 'county' => $address_data['county'],
            // 'city_code' => $address_data['city_code'],
        ];

        return $this->success($result);
    }

    /**
     * 地址搜索
     * @data 2023/8/11 3:29 下午
     */
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
                    if (isset($v['district']) && isset($v['address']) && is_string($v['district']) && is_string($v['address'])) {
                        $result[] = [
                            'address' => $v['district'] . ',' .$v['address'],
                            // 'address' => $v['address'],
                            'lng' => $location[0],
                            'lat' => $location[1],
                            'name' => $v['name'],
                        ];
                    }
                }
            }
        }

        return $this->success($result);
    }

    /**
     * 自配送订单完成
     * @data 2023/9/14 2:41 下午
     */
    public function finish(Request $request)
    {
        $order_id = (int) $request->get("order_id", 0);
        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }
        // 判断权限
        // if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        if (true) {
            if (!in_array($order->shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('订单不存在!');
            }
        }
        if ($order->status == 70 || $order->status == 75) {
            return $this->success();
        }
        if ($order->status != 60) {
            return $this->error('该订单不能完成');
        }
        $order->status = 70;
        $order->over_at = date("Y-m-d H:i:s");
        $order->courier_lng = $order->receiver_lng;
        $order->courier_lat = $order->receiver_lat;
        $order->save();
        // 跑腿运力完成
        OrderDelivery::finish_log($order->id, 200, 'APP操作');
        dispatch(new MtLogisticsSync($order));
        return $this->success();
    }
}
