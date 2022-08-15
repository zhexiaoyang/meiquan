<?php

namespace App\Http\Controllers;

use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderSetting;
use App\Models\Shop;
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

    /**
     * 忽略配送订单
     * @param Order $order
     * @return mixed
     * @author zhangzhen
     * @data 2022/8/15 10:14 下午
     */
    public function ignore(Order $order)
    {
        $order->status = -5;
        $order->save();

        return $this->success();
    }

    /**
     * 订单派送
     * @param Order $order
     * @return mixed
     * @author zhangzhen
     * @data 2022/8/15 10:14 下午
     */
    public function advance(Order $order)
    {
        if (!$shop = Shop::with('shippers')->find($order->shop_id)) {
            return $this->error("门店不存在");
        }
        $result = [
            'id' => $order->id,
            'receiver_name' => $order->receiver_name,
            'receiver_address' => $order->receiver_address,
            'receiver_phone' => $order->receiver_phone,
            'expected_delivery_time' => $order->expected_delivery_time,
            'day_seq' => $order->day_seq,
            'ctime' => $order->ctime,
            'wm_poi_name' => $order->wm_poi_name,
            'shop_name' => $order->shop_name,
            'mt_status' => $order->mt_status,
            'fn_status' => $order->fn_status,
            'ss_status' => $order->ss_status,
            'mqd_status' => $order->mqd_status,
            'dd_status' => $order->dd_status,
            'uu_status' => $order->uu_status,
            'sf_status' => $order->sf_status,
        ];
        // 加价金额
        $add_money = $shop->running_add;
        // 聚合运力
        if ($shop->shop_id) {
            $meituan = app("meituan");
            $check_mt = $meituan->preCreateByShop($shop, $order);
            if (isset($check_mt['data']['delivery_fee']) && $check_mt['data']['delivery_fee'] > 0) {
                $result['mt'] = $shop->shop_id;
                $result['mt_type'] = 1;
                $result['mt_money'] = $check_mt['data']['delivery_fee'] + $add_money;
            }
        }
        if ($shop->shop_id_fn) {
            $fengniao = app("fengniao");
            $check_fn_res = $fengniao->preCreateOrderNew($shop, $order);
            $check_fn = json_decode($check_fn_res['business_data'], true);
            if (isset($check_fn['goods_infos'][0]['actual_delivery_amount_cent']) && $check_fn['goods_infos'][0]['actual_delivery_amount_cent'] > 0) {
                $result['fn'] = $shop->shop_id_fn;
                $result['fn_type'] = 1;
                $result['fn_money'] = (($check_fn['goods_infos'][0]['actual_delivery_amount_cent'] ?? 0) + ($add_money * 100) ) / 100;
            }
        }
        if ($shop->shop_id_ss) {
            $shansong = app("shansong");
            $check_ss = $shansong->orderCalculate($shop, $order);
            if (isset($check_ss['data']['totalFeeAfterSave']) && $check_ss['data']['totalFeeAfterSave'] > 0) {
                $result['ss'] = $shop->shop_id_ss;
                $result['ss_type'] = 1;
                $result['ss_money'] = (($check_ss['data']['totalFeeAfterSave'] ?? 0) / 100) + $add_money;
            }
        }
        if ($shop->shop_id_dd) {
            $dada = app("dada");
            $check_dd= $dada->orderCalculate($shop, $order);
            if (isset($check_dd['result']['fee']) && $check_dd['result']['fee'] > 0) {
                $result['dd'] = $shop->shop_id_dd;
                $result['dd_type'] = 1;
                $result['dd_money'] = $check_dd['result']['fee'] + $add_money;
            }
        }
        if ($shop->shop_id_mqd) {
            $meiquanda = app('meiquanda');
            $check_mqd = $meiquanda->orderCalculate($shop, $order);
            if (isset($check_mqd['data']['pay_fee']) && $check_mqd['data']['pay_fee'] > 0) {
                $result['mqd'] = $shop->shop_id_mqd;
                $result['mqd_type'] = 1;
                $result['mqd_money'] = $check_mqd['data']['pay_fee'] + $add_money;
            }
        }
        if ($shop->shop_id_uu) {
            $uu = app("uu");
            $check_uu= $uu->orderCalculate($order, $shop);
            if (isset($check_uu['need_paymoney']) && $check_uu['need_paymoney'] > 0) {
                $result['uu'] = $shop->shop_id_uu;
                $result['uu_type'] = 1;
                $result['uu_money'] = $check_uu['need_paymoney'] + $add_money;
            }
        }
        if ($shop->shop_id_sf) {
            $sf = app("shunfeng");
            $check_sf= $sf->precreateorder($order, $shop);
            if (isset($check_sf['result']['real_pay_money']) && $check_sf['result']['real_pay_money'] > 0) {
                $result['sf'] = $shop->shop_id_sf;
                $result['sf_type'] = 1;
                $result['sf_money'] = (($check_sf['result']['real_pay_money'] ?? 0) / 100) + $add_money;
            }
        }
        // 自有运力列表
        if (!empty($shop->shippers)) {
            foreach ($shop->shippers as $shipper) {
                if ($shipper->platform == 3) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                    $check_ss = $shansong->orderCalculate($shop, $order);
                    if (isset($check_ss['data']['totalFeeAfterSave']) && $check_ss['data']['totalFeeAfterSave'] > 0) {
                        $result['ss'] = $shipper->three_id;
                        $result['ss_type'] = 3;
                        $result['ss_money'] = (($check_ss['data']['totalFeeAfterSave'] ?? 0) / 100);
                    }
                }
                if ($shipper->platform == 5) {
                    $config = config('ps.dada');
                    $config['source_id'] = $shipper->source_id;
                    $dada = new DaDaService($config);
                    $check_dd= $dada->orderCalculate($shop, $order);
                    if (isset($check_dd['result']['fee']) && $check_dd['result']['fee'] > 0) {
                        $result['dd'] = $shipper->three_id;
                        $result['dd_type'] = 2;
                        $result['dd_money'] = $check_dd['result']['fee'];
                    }
                }
                if ($shipper->platform == 7) {
                    $sf = app("shunfengservice");
                    $check_sf= $sf->precreateorder($order, $shop);
                    if (isset($check_sf['result']['real_pay_money']) && $check_sf['result']['real_pay_money'] > 0) {
                        $result['sf'] = $shipper->three_id;
                        $result['sf_type'] = 2;
                        $result['sf_money'] = (($check_sf['result']['real_pay_money'] ?? 0) / 100) + $add_money;
                    }
                }
            }
        }

        return $this->success($result);
    }

    public function cancel(Order $order)
    {
        return $this->success();
    }
}
