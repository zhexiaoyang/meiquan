<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;

class ShoppingStatisticController extends Controller
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

        $query = SupplierOrder::query()->select("status","receive_shop_id","total_fee","mq_charge_fee")
            ->where("status", '>=', 70)->where("created_at", ">", $start_date)
            ->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        // 判断可以查询的药店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('receive_shop_id', $request->user()->shops()->pluck('id'));
        }

        $orders = $query->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->status === 70) {
                    $res['complete'] += 1;
                    $res['money'] += ($order->total_fee * 100);
                    $res['profit'] += ($order->mq_charge_fee * 100);
                }
                if ($order->status === 90) {
                    $res['cancel'] += 1;
                }
            }
        }
        $res['money'] /= 100;
        $res['profit'] /= 100;

        return $this->success($res);
    }
}
