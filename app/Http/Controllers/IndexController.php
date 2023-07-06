<?php

namespace App\Http\Controllers;

use App\Models\ContractOrder;
use App\Models\OnlineShop;
use App\Models\OnlineShopLog;
use App\Models\Order;
use App\Models\Shop;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 中台首页数据
 */
class IndexController extends Controller
{
    /**
     * 卡片数据
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/10/23 12:12 上午
     */
    public function card(Request $request)
    {
        $order_query = Order::where("over_at", ">", date("Y-m-d"))->where("status", 70);
        $shop_query = Shop::where("user_id", '>', 0);
        $shop_no_auto_query = Shop::where("user_id", '>', 0);
        $online_query = OnlineShop::where("status", 40);
        $supplier_query = SupplierOrder::whereIn("status", [30, 50]);

        // 判断可以查询的药店
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $order_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $shop_query->whereIn('id', $request->user()->shops()->pluck('id'));
            $shop_no_auto_query->whereIn('id', $request->user()->shops()->pluck('id'));
            $online_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $supplier_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $supplier_new_orders = SupplierOrder::select('receive_shop_id')->where('created_at', '>=', date("Y-m-01"))->get();
        $old_ids = SupplierOrder::select('receive_shop_id')->where('created_at', '<', date("Y-m-01"))->pluck('receive_shop_id')->toArray();
        $supplier_new = 0;
        if (!empty($supplier_new_orders)) {
            foreach ($supplier_new_orders as $supplier_new_order) {
                if (!in_array($supplier_new_order->receive_shop_id, $old_ids)) {
                    $supplier_new++;
                }
            }
        }

        $res = [
            "order" => $order_query->count(),
            "shop" => $shop_query->count(),
            "shop_new" => $shop_query->where('created_at', '>=', date("Y-m-01"))->count(),
            "shop_no_auth" => $shop_no_auto_query->where('auth', '<', 10)->count(),
            "online" => $online_query->count(),
            "supplier" => $supplier_query->count(),
            "supplier_new" => $supplier_new,
        ];

        return $this->success($res);
    }

    /**
     * 可签合同数量
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/10/23 12:12 上午
     */
    public function contract(Request $request)
    {
        $number = ContractOrder::where("user_id", $request->user()->id)->where("online_shop_id", 0)->count();

        return $this->status(compact("number"));
    }

    /**
     * 门店订单统计
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/10/23 12:11 上午
     */
    public function order(Request $request)
    {
        $start_date = $request->get("start_date", date("Y-m-d"));
        $end = $request->get("end_date", date("Y-m-d"));
        $end_date = date("Y-m-d", strtotime($end) + 86400);
        // select shop_id,count(*) as count,status from orders where created_at > '2021-10-21' group by shop_id,status order by shop_id desc;
        $complete_query = Order::with(["shop" => function ($query) {
            $query->select("id", "shop_name");
        }])->select("shop_id", DB::raw("count(id) as count"), "status")
            ->where("status", 70)
            ->where("over_at", ">=", $start_date)
            ->where("over_at", "<", $end_date)
            ->groupBy("shop_id", "status");
        $cancel_query = Order::with(["shop" => function ($query) {
            $query->select("id", "shop_name");
        }])->select("shop_id", DB::raw("count(id) as count"), "status")
            ->where("status", ">=", 80)
            ->where("cancel_at", ">=", $start_date)
            ->where("cancel_at", "<", $end_date)
            ->groupBy("shop_id", "status");
        $exception_query = Order::with(["shop" => function ($query) {
            $query->select("id", "shop_name");
        }])->select("shop_id", DB::raw("count(id) as count"), "status")
            ->whereIn("status", [0,5,7,10])
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<", $end_date)
            ->groupBy("shop_id", "status");
        // 判断可以查询的药店
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $complete_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $cancel_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $exception_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }
        $complete_orders = $complete_query->get();
        $cancel_orders = $cancel_query->get();
        $exception_orders = $exception_query->get();

        $data = [];
        $count = 0;
        $complete = 0;
        $cancel = 0;
        $exception = 0;

        if (!empty($complete_orders)) {
            foreach ($complete_orders as $order) {
                $data[$order->shop_id]['shop_id'] = $order->shop_id;
                $data[$order->shop_id]['shop_name'] = $order->shop->shop_name ?? "";
                if (!isset($data[$order->shop_id]['cancel'])) {
                    $data[$order->shop_id]['cancel'] = 0;
                }
                if (!isset($data[$order->shop_id]['complete'])) {
                    $data[$order->shop_id]['complete'] = 0;
                }
                if (!isset($data[$order->shop_id]['exception'])) {
                    $data[$order->shop_id]['exception'] = 0;
                }
                if (!isset($data[$order->shop_id]['count'])) {
                    $data[$order->shop_id]['count'] = 0;
                }
                $data[$order->shop_id]['complete'] = $order->count;
                $data[$order->shop_id]['count'] += $order->count;
                $complete += $order->count;
                $count += $order->count;
            }
        }

        if (!empty($cancel_orders)) {
            foreach ($cancel_orders as $order) {
                $data[$order->shop_id]['shop_id'] = $order->shop_id;
                $data[$order->shop_id]['shop_name'] = $order->shop->shop_name ?? "";
                if (!isset($data[$order->shop_id]['cancel'])) {
                    $data[$order->shop_id]['cancel'] = 0;
                }
                if (!isset($data[$order->shop_id]['complete'])) {
                    $data[$order->shop_id]['complete'] = 0;
                }
                if (!isset($data[$order->shop_id]['exception'])) {
                    $data[$order->shop_id]['exception'] = 0;
                }
                if (!isset($data[$order->shop_id]['count'])) {
                    $data[$order->shop_id]['count'] = 0;
                }
                $data[$order->shop_id]['cancel'] = $order->count;
                $data[$order->shop_id]['count'] += $order->count;
                $cancel += $order->count;
                $count += $order->count;
            }
        }

        if (!empty($exception_orders)) {
            foreach ($exception_orders as $order) {
                $data[$order->shop_id]['shop_id'] = $order->shop_id;
                $data[$order->shop_id]['shop_name'] = $order->shop->shop_name ?? "";
                if (!isset($data[$order->shop_id]['cancel'])) {
                    $data[$order->shop_id]['cancel'] = 0;
                }
                if (!isset($data[$order->shop_id]['complete'])) {
                    $data[$order->shop_id]['complete'] = 0;
                }
                if (!isset($data[$order->shop_id]['exception'])) {
                    $data[$order->shop_id]['exception'] = 0;
                }
                if (!isset($data[$order->shop_id]['count'])) {
                    $data[$order->shop_id]['count'] = 0;
                }
                $data[$order->shop_id]['exception'] = $order->count;
                $data[$order->shop_id]['count'] += $order->count;
                $exception += $order->count;
                $count += $order->count;
            }
        }

        $result = [
            'data' => $attr = collect(array_values($data))->sortByDesc('count')->values()->all(),
            'count' => $count,
            'complete' => $complete,
            'cancel' => $cancel,
            'exception' => $exception,
        ];

        return $this->success($result);
    }

    /**
     * 城市经理-跑腿门店-跑腿订单数据
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/10/23 12:13 上午
     */
    public function city_manager_running(Request $request)
    {
        $start_date = $request->get("start_date", date("Y-m-d"));
        $end = $request->get("end_date", date("Y-m-d"));
        $end_date = date("Y-m-d", strtotime($end) + 86400);
        $result = [];

        $users = User::with(['commission', 'shops' => function ($query) {
            $query->select("id");
        }])->select("id","name","phone","nickname","status")
            ->whereHas('roles', function ($query) {
                $query->where('name', 'city_manager');
            })->where("status", 1)->get();

        if (!empty($users)) {
            foreach ($users as $user) {
                $shop_ids = empty($user->shops) ? [] : $user->shops->pluck("id");
                $orders = Order::select(DB::raw("SUM(money) as money_sum"),DB::raw("COUNT(id) as order_count"))
                    ->where("over_at", ">=", $start_date)->where("over_at", "<", $end_date)
                    ->whereIn('shop_id', $shop_ids)
                    ->where("status", 70)->first();
                $profit = 0;
                if (isset($user->commission->running_type)) {
                    if ($user->commission->running_type === 1) {
                        $profit = number_format($orders->order_count * $user->commission->running_value1, 2);
                    }
                    if ($user->commission->running_type === 2) {
                        $profit = number_format($orders->money_sum * $user->commission->running_value2, 2);
                    }
                }
                $result[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'nickname' => $user->nickname,
                    'status' => $user->status,
                    'shop_count' => count($user->shops),
                    'shop_add' => Shop::whereIn('id', $shop_ids)->where('created_at', '>', date("Y-m-1"))->count(),
                    'running_type' => $user->commission->running_type ?? 0,
                    'running_value1' => $user->commission->running_value1 ?? 0,
                    'running_value2' => $user->commission->running_value2 ?? 0,
                    'shop_ids' => $shop_ids,
                    'money_sum' => $orders->money_sum,
                    'order_count' => $orders->order_count,
                    'profit' => $profit,
                ];
            }
        }

        return $this->success(collect($result)->sortByDesc('shop_count')->values()->all());
    }

    /**
     * 城市经理-跑腿门店-外卖资料
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/10/23 12:13 上午
     */
    public function city_manager_online(Request $request)
    {
        $start_date = $request->get("start_date", date("Y-m-1"));
        $end = $request->get("end_date", date("Y-m-d"));
        $end_date = date("Y-m-d", strtotime($end) + 86400);
        $result = [];

        $users = User::with(['shops' => function ($query) {
            $query->select("id");
        }])->select("id","name","phone","nickname","status")
            ->whereHas('roles', function ($query) {
                $query->where('name', 'city_manager');
            })->where("status", 1)->get();

        if (!empty($users)) {
            foreach ($users as $user) {
                $total = 0;
                $example = 0;
                $return_back = 0;
                $shop_ids = empty($user->shops) ? [] : $user->shops->pluck("id");
                $onlines = OnlineShop::select("id","status")
                    ->where("created_at", ">=", $start_date)->where("created_at", "<", $end_date)
                    ->whereIn('shop_id', $shop_ids)->get();
                if (!empty($onlines)) {
                    $total = $onlines->count();
                    if (strtotime($end) >= strtotime('2021-10-18')) {
                        $online_logs = OnlineShopLog::query()
                            ->where("date", $end)
                            ->whereIn("online_shop_id", $onlines->pluck("id"))->get();
                        if (!empty($online_logs)) {
                            foreach ($online_logs as $log) {
                                if ($log->status === 20) {
                                    $return_back++;
                                }
                                if ($log->status <= 10) {
                                    $example++;
                                }
                            }
                        }
                    } else {
                        foreach ($onlines as $online) {
                            if ($online->status === 20) {
                                $return_back++;
                            }
                            if ($online->status <= 10) {
                                $example++;
                            }
                        }
                    }
                }
                $result[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'nickname' => $user->nickname,
                    'status' => $user->status,
                    'total' => $total,
                    'complete' => $total - $example - $return_back,
                    'example' => $example,
                    'return' => $return_back,
                ];
            }
        }

        // return $this->success($result);
        return $this->success(collect($result)->sortByDesc('total')->values()->all());
    }
}
