<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserReturn;
use Illuminate\Http\Request;

class RunningStatisticController extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->get("start_date", date("Y-m-d"));
        $end_date = $request->get("end_date", date("Y-m-d"));
        $res = [
            'complete' => 0,
            'cancel' => 0,
            'money' => 0,
            'profit' => 0,
        ];

        if (!$start_date || !$end_date) {
            return $this->success($res);
        }

        $query = Order::query()->select("id", "shop_id", "status", "money")
            ->where("status", 70)->where("over_at", ">", $start_date)
            ->where("over_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        $cancel_query = Order::query()->where("cancel_at", ">=", $start_date)->where("status", 99)
            ->where("cancel_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        // 判断可以查询的药店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            $cancel_query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $orders = $query->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->status === 70) {
                    $res['complete'] += 1;
                    $res['money'] += $order->money * 100;
                    $res['profit'] += 1;
                }
            }
        }
        $res['money'] /= 100;
        $res['cancel'] = $cancel_query->count();

        if ($res['profit'] > 0 && !$request->user()->hasRole('super_man')) {
            if ($user_return = UserReturn::where("user_id", $request->user()->id)->first()) {
                if ($user_return->running_type === 1) {
                    $res['profit'] = number_format($res['complete'] * $user_return['running_value1'], 2);
                } else {
                    $res['profit'] = number_format($res['money'] * $user_return['running_value2'] / 100,2);
                }
            } else {
                $res['profit'] = 0;
            }
        }

        return $this->success($res);
    }
}
