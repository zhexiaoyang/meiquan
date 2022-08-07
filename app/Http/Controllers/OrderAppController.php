<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderAppController extends Controller
{
    /**
     * 首页
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/8/7 11:54 上午
     */
    public function index_status(Request $request)
    {
        $page_size = $request->get('page_size', 30);
        $shop_id = $request->get('shop_id', 0);
        $search_key = $request->get('search_key', '');
        $status = $request->get('status');

        // if (!in_array($status, [0, 20, 50, 60, 'dai', 'yichang', 'cui'])) {
        //     return $this->error('参数错误');
        // }

        // 查询数据
        $query = Order::with(['shop' => function($query) {
            $query->select('id', 'shop_id', 'shop_name');
        }, 'warehouse' => function($query) {
            $query->select('id', 'shop_id', 'shop_name');
        }, 'products' => function($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'spec', 'price');
        }, 'order' => function($query) {
            $query->select('id', 'order_id', 'ctime', 'estimate_arrival_time');
        }, 'logs'])->select('id','shop_id','order_id','peisong_id','receiver_name','receiver_phone','money','failed',
            'receiver_address','tool','ps',
            'mt_status','money_mt','fail_mt',
            'fn_status','money_fn','fail_fn',
            'ss_status','money_ss','fail_ss',
            'mqd_status','money_mqd','fail_mqd',
            'dd_status','money_dd','fail_dd',
            'uu_status','money_uu','fail_uu',
            'sf_status','money_sf','fail_sf',
            'courier_name','courier_phone','warehouse_id','day_seq','wm_poi_name','caution','wm_id',
            'send_at','created_at','over_at','cancel_at','receive_at','take_at','goods_pickup_info',
            'platform','receiver_lng','expected_delivery_time','receiver_lat','status','expected_send_time');

        // 关键字搜索
        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('delivery_id', 'like', "%{$search_key}%")
                    ->orWhere('order_id', 'like', "%{$search_key}%")
                    ->orWhere('peisong_id', 'like', "%{$search_key}%")
                    ->orWhere('receiver_name', 'like', "%{$search_key}%")
                    ->orWhere('receiver_phone', 'like', "%{$search_key}%");
            });
        }

        if ($shop_id) {
            $query->where("shop_id", $shop_id);
        }

        // 判断可以查询的药店
        // if (!$request->user()->hasRole('super_man')) {
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        // 状态查询
        if (!is_null($status)) {
            if (is_numeric($status)) {
                $query->where('status', $status);
            } else {
                if ($status === 'fa') {
                    $query->whereIn("status", [3,8]);
                } elseif ($status === 'yichang') {
                    $query->whereIn("status", [5,7,10]);
                }
            }
        }

        // 查询订单
        $orders = $query->withCount(['products as products_sum' => function($query){
            $query->select(DB::raw("sum(quantity) as products_sum"));
        }])->where('created_at', '>', date("Y-m-d H:i:s", time() - 86400 * 2))
            ->where('status', '>', -10)->orderBy('id', 'desc')->paginate($page_size);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (in_array($order->status, [3,8,20 ,30 ,40 ,50 ,60])) {
                    $order->is_cancel = 1;
                } else {
                    $order->is_cancel = 0;
                }
                // $order->status_code = $order->status;
                // $order->status = $order->status_label;
                if (isset($order->shop->shop_name)) {
                    $order->shop_name = $order->shop->shop_name;
                } else {
                    $order->shop_name = "";
                }
                if (isset($order->warehouse->shop_name)) {
                    $order->warehouse_name = $order->warehouse->shop_name;
                } else {
                    $order->warehouse = "";
                }
                $order->delivery = $order->expected_delivery_time > 0 ? date("m-d H:i", $order->expected_delivery_time) : "";
                $number = 0;
                if (!empty($order->send_at) && ($second = strtotime($order->send_at)) > 0) {
                    if ($setting = OrderSetting::query()->where("shop_id", $order->shop_id)->first()) {
                        $ttl = $setting->delay_send;
                    } else {
                        $ttl = config("ps.shop_setting.delay_send");
                    }
                    $number = $second - time() + $ttl > 0 ? $second - time() + $ttl : 0;
                }
                // if ($order->status == 8 && $number == 0 ) {
                //     $order->status = 0;
                // }
                $estimate_arrival_time = $order->order->estimate_arrival_time ?? 0;
                if ($estimate_arrival_time) {
                    $estimate_arrival_time = strtotime(date("Y-m-d H:i", $estimate_arrival_time));
                }
                // 发单倒计时
                $order->number = $number;
                // 接单时间
                $order->receive_time = strtotime($order->receive_at);
                $order->ctime = $order->order->ctime ?? strtotime($order->created_at);
                $order->estimate_arrival_time = $estimate_arrival_time;
                $order->current_time = time();
                // 下单几分钟
                $order->create_pass = ceil((time() - $order->ctime) / 60);
                // 接单几分钟
                $order->receive_pass = ceil((time() - $order->receive_time) / 60);
                $order->arrival_pass = $order->estimate_arrival_time > 0 ? (ceil(($estimate_arrival_time - time()) / 60)) : 0;

                unset($order->order);
                unset($order->shop);
                unset($order->warehouse);
            }
        }
        return $this->success($orders);
    }

    /**
     * APP 个状态订单统计
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/8/2 11:25 下午
     */
    public function index_statistics(Request $request)
    {
        $orders = Order::select(DB::raw('
            count(status=0 or null) as xin,
            count(status=8 or status=3 or null) as fa,
            count(status=20 or null) as wei,
            count(status=50 or null) as qu,
            count(status=60 or null) as song,
            count(status=5 or status=7 or status=10 or null) as yi,
            count(status=null) as cui
        '))
            // count(status=null) as tui,
            ->whereIn('status', [0, 20, 50, 60, 3,8,5,7])
            ->where('created_at', '>=', date("Y-m-d H:i:s", time() - 86400 * 2))
            ->first()->toArray();

        foreach ($orders as $k => $v) {
            if (!$v) {
                $orders[$k] = '-';
            }
        }

        return $this->success($orders);
    }

    public function ignore(Request $request, Order $order)
    {
        $order->status = -5;
        $order->save();

        return $this->success();
    }
}
