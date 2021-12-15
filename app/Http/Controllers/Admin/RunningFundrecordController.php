<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\RunningFundrecordExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class RunningFundrecordController extends Controller
{
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;

        if (!$start_date = $request->get("start_date")) {
            return $this->error("开始日期不能为空");
        }
        if (!$end_date = $request->get("end_date")) {
            return $this->error("结束日期不能为空");
        }

        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        $end_date = date("Y-m-d", $end_time + 86400);

        if ($start_time > $end_time) {
            return $this->error("起始时间不能大于结束时间");
        }

        $day = ($end_time - $start_time) / 86400 + 1;

        if ($day > 31) {
            return $this->error("时间范围不能超过31天");
        }

        $where = [
            ["status", 70],
            ["over_at", '>', $start_date],
            ["over_at", '<', $end_date],
        ];

        $orders = Order::with(['shop', 'deduction'])->where($where)->orderByDesc("over_at")->paginate($page_size);

        $data = [];
        $total_money = Order::where($where)->sum('money');
        $total_number = Order::where($where)->count();
        $total_profit = Order::where($where)->sum('profit');

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $data[] = [
                    'id' => $order->id,
                    'shop_name' => $order->shop->shop_name,
                    'order_id' => $order->order_id,
                    'delivery_fee' => $order->money,
                    'cancel_fee' => $order->deduction,
                    'profit' => $order->profit,
                    'over_at' => $order->over_at,
                ];
            }
        }

        $res['total_profit'] = $total_profit;
        $res['avg_profit'] = sprintf("%.2f", $total_profit / $day);
        $res['total_money'] = $total_money;
        $res['avg_money'] = sprintf("%.2f", $total_money / $day);
        $res['total_number'] = $total_number;
        $res['avg_number'] = intval($total_number / $day);
        $res['page'] = $orders->currentPage();
        $res['total'] = $orders->total();
        $res['list'] = $data;

        return $this->success($res);
    }

    public function export(Request $request, RunningFundrecordExport $export)
    {
        return $export->withRequest($request);
    }
}
