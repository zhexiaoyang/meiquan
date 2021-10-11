<?php

namespace App\Http\Controllers;

use App\Exports\OrderStatisticsExport;
use App\Models\Order;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{

    public function index(Request $request)
    {
        $total_money = 0;
        $total_num = 0;
        $mt_money = 0;
        $mt_num = 0;
        $fn_money = 0;
        $fn_num = 0;
        $ss_money = 0;
        $ss_num = 0;
        $dd_money = 0;
        $dd_num = 0;
        $mqd_money = 0;
        $mqd_num = 0;
        $uu_money = 0;
        $uu_num = 0;
        $sf_money = 0;
        $sf_num = 0;
        $today_total_money = 0;
        $today_total_num = 0;
        $today_mt_money = 0;
        $today_mt_num = 0;
        $today_fn_money = 0;
        $today_fn_num = 0;
        $today_ss_money = 0;
        $today_ss_num = 0;
        $today_dd_money = 0;
        $today_dd_num = 0;
        $today_mqd_money = 0;
        $today_mqd_num = 0;
        $today_uu_money = 0;
        $today_uu_num = 0;
        $today_sf_money = 0;
        $today_sf_num = 0;
        $date_num = [];
        $date_money = [];

        $type = $request->get("type", "");
        $start_date = $request->get("start_date", "");
        $end_date = $request->get("end_date", "");

        if ($type == "a") {
            $start_date = date("Y-m-01");
            $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
        } elseif ($type == "b") {
            $start_date = date("Y-m-d",strtotime("this week"));
            $end_date = date('Y-m-d', strtotime($start_date) + 86400 * 7);
        } elseif ($type == "c") {
            $start_date = date('Y-m-01', strtotime('-1 month'));
            $end_date = date('Y-m-t', strtotime('-1 month'));
        }

        $start = strtotime($start_date);
        $end = strtotime($end_date);

        while ($start <= $end) {
            $date_num[date("Y-m-d", $start)] = ["x" => date("m-d", $start), "y" => 0];
            $date_money[date("Y-m-d", $start)] = ["x" => date("m-d", $start), "y" => 0];
            $start += 86400;
        }

        $query = Order::query()->select("id","shop_id","ps","money","over_at")->where("status", 70)
            ->where("over_at", ">=", $start_date)
            ->where("over_at", "<", date("Y-m-d", strtotime($end_date) + 86400));

        if (!$request->user()->hasRole('super_man')) {
            $_shop_ids = $request->user()->shops()->pluck('id') ?? [];
            $query->whereIn('shop_id', $_shop_ids);
        }

        $orders = $query->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $total_money += $order->money * 100;
                $total_num++;
                $date_money[date("Y-m-d", strtotime($order->over_at))]["y"] += $order->money * 100;
                $date_num[date("Y-m-d", strtotime($order->over_at))]["y"]++;

                if ($order->ps == 1) {
                    $mt_num++;
                    $mt_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_mt_num++;
                        $today_mt_money += $order->money * 100;
                    }
                }elseif ($order->ps == 2) {
                    $fn_num++;
                    $fn_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_fn_num++;
                        $today_fn_money += $order->money * 100;
                    }
                }elseif ($order->ps == 3) {
                    $ss_num++;
                    $ss_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_ss_num++;
                        $today_ss_money += $order->money * 100;
                    }
                }elseif ($order->ps == 4) {
                    $mqd_num++;
                    $mqd_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_mqd_num++;
                        $today_mqd_money += $order->money * 100;
                    }
                }elseif ($order->ps == 5) {
                    $dd_num++;
                    $dd_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_dd_num++;
                        $today_dd_money += $order->money * 100;
                    }
                }elseif ($order->ps == 6) {
                    $uu_num++;
                    $uu_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_uu_num++;
                        $today_uu_money += $order->money * 100;
                    }
                }elseif ($order->ps == 7) {
                    $sf_num++;
                    $sf_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->over_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_sf_num++;
                        $today_sf_money += $order->money * 100;
                    }
                }
            }
        }

        foreach ($date_money as $k => $v) {
            $date_money[$k]['y'] = $v['y'] / 100;
        }

        $res = [
            "total_money" => $total_money / 100,
            "total_num" => $total_num,
            "mt_money" => $mt_money / 100,
            "mt_num" => $mt_num,
            "fn_money" => $fn_money / 100,
            "fn_num" => $fn_num,
            "ss_money" => $ss_money / 100,
            "ss_num" => $ss_num,
            "dd_money" => $dd_money / 100,
            "dd_num" => $dd_num,
            "mqd_money" => $mqd_money / 100,
            "mqd_num" => $mqd_num,
            "uu_money" => $uu_money / 100,
            "uu_num" => $uu_num,
            "sf_money" => $sf_money / 100,
            "sf_num" => $sf_num,
            "today_total_money" => $today_total_money / 100,
            "today_total_num" => $today_total_num,
            "today_mt_money" => $today_mt_money / 100,
            "today_mt_num" => $today_mt_num,
            "today_fn_money" => $today_fn_money / 100,
            "today_fn_num" => $today_fn_num,
            "today_ss_money" => $today_ss_money / 100,
            "today_ss_num" => $today_ss_num,
            "today_dd_money" => $today_dd_money / 100,
            "today_dd_num" => $today_dd_num,
            "today_mqd_money" => $today_mqd_money / 100,
            "today_mqd_num" => $today_mqd_num,
            "today_uu_money" => $today_uu_money / 100,
            "today_uu_num" => $today_uu_num,
            "today_sf_money" => $today_sf_money / 100,
            "today_sf_num" => $today_sf_num,
            "date_num" => array_values($date_num),
            "date_money" => array_values($date_money),

        ];

        return $this->success($res);
    }

    public function export(Request $request, OrderStatisticsExport $orderStatisticsExport)
    {
        return $orderStatisticsExport->withRequest($request);
    }

    // public function detail(Request $request, OrdersExport $ordersExport)
    // {
    //     return $ordersExport->withRequest($request);
    // }

}
