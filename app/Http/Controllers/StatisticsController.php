<?php

namespace App\Http\Controllers;

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
        $today_total_money = 0;
        $today_total_num = 0;
        $today_mt_money = 0;
        $today_mt_num = 0;
        $today_fn_money = 0;
        $today_fn_num = 0;
        $today_ss_money = 0;
        $today_ss_num = 0;
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

        \Log::info('message', $request->all());
        \Log::info('message', [$date_num]);
        \Log::info('message', [$date_money]);


        // $date_num = [
        //     1 => ["x" => "1日", "y" => 0], 2 => ["x" => "2日", "y" => 0], 3 => ["x" => "3日", "y" => 0], 4 => ["x" => "4日", "y" => 0],
        //     5 => ["x" => "5日", "y" => 0], 6 => ["x" => "6日", "y" => 0], 7 => ["x" => "7日", "y" => 0], 8 => ["x" => "8日", "y" => 0],
        //     9 => ["x" => "9日", "y" => 0], 10 => ["x" => "10日", "y" => 0], 11 => ["x" => "11日", "y" => 0], 12 => ["x" => "12日", "y" => 0],
        //     13 => ["x" => "13日", "y" => 0], 14 => ["x" => "14日", "y" => 0], 15 => ["x" => "15日", "y" => 0], 16 => ["x" => "16日", "y" => 0],
        //     17 => ["x" => "17日", "y" => 0], 18 => ["x" => "18日", "y" => 0], 19 => ["x" => "19日", "y" => 0], 20 => ["x" => "20日", "y" => 0],
        //     21 => ["x" => "21日", "y" => 0], 22 => ["x" => "22日", "y" => 0], 23 => ["x" => "23日", "y" => 0], 24 => ["x" => "24日", "y" => 0],
        //     25 => ["x" => "25日", "y" => 0], 26 => ["x" => "26日", "y" => 0], 27 => ["x" => "27日", "y" => 0], 28 => ["x" => "28日", "y" => 0],
        //     29 => ["x" => "29日", "y" => 0], 30 => ["x" => "30日", "y" => 0], 31 => ["x" => "31日", "y" => 0]
        // ];
        //
        // $date_money = [
        //     1 => ["x" => "1日", "y" => 0], 2 => ["x" => "2日", "y" => 0], 3 => ["x" => "3日", "y" => 0], 4 => ["x" => "4日", "y" => 0],
        //     5 => ["x" => "5日", "y" => 0], 6 => ["x" => "6日", "y" => 0], 7 => ["x" => "7日", "y" => 0], 8 => ["x" => "8日", "y" => 0],
        //     9 => ["x" => "9日", "y" => 0], 10 => ["x" => "10日", "y" => 0], 11 => ["x" => "11日", "y" => 0], 12 => ["x" => "12日", "y" => 0],
        //     13 => ["x" => "13日", "y" => 0], 14 => ["x" => "14日", "y" => 0], 15 => ["x" => "15日", "y" => 0], 16 => ["x" => "16日", "y" => 0],
        //     17 => ["x" => "17日", "y" => 0], 18 => ["x" => "18日", "y" => 0], 19 => ["x" => "19日", "y" => 0], 20 => ["x" => "20日", "y" => 0],
        //     21 => ["x" => "21日", "y" => 0], 22 => ["x" => "22日", "y" => 0], 23 => ["x" => "23日", "y" => 0], 24 => ["x" => "24日", "y" => 0],
        //     25 => ["x" => "25日", "y" => 0], 26 => ["x" => "26日", "y" => 0], 27 => ["x" => "27日", "y" => 0], 28 => ["x" => "28日", "y" => 0],
        //     29 => ["x" => "29日", "y" => 0], 30 => ["x" => "30日", "y" => 0], 31 => ["x" => "31日", "y" => 0]
        // ];

        $query = Order::select("id","shop_id","ps","money","created_at")->where("status", 70)
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));

        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $orders = $query->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $total_money += $order->money * 100;
                $total_num++;
                $date_money[date("Y-m-d", strtotime($order->created_at))]["y"] += $order->money * 100;
                $date_num[date("Y-m-d", strtotime($order->created_at))]["y"]++;

                if ($order->ps == 1) {
                    $mt_num++;
                    $mt_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->created_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_mt_num++;
                        $today_mt_money += $order->money * 100;
                    }
                }elseif ($order->ps == 2) {
                    $fn_num++;
                    $fn_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->created_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_fn_num++;
                        $today_fn_money += $order->money * 100;
                    }
                }elseif ($order->ps == 3) {
                    $ss_num++;
                    $ss_money += $order->money * 100;
                    if (date("Y-m-d") == date("Y-m-d", strtotime($order->created_at))) {
                        $today_total_num++;
                        $today_total_money += $order->money * 100;
                        $today_ss_num++;
                        $today_ss_money += $order->money * 100;
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
            "today_total_money" => $today_total_money / 100,
            "today_total_num" => $today_total_num,
            "today_mt_money" => $today_mt_money / 100,
            "today_mt_num" => $today_mt_num,
            "today_fn_money" => $today_fn_money / 100,
            "today_fn_num" => $today_fn_num,
            "today_ss_money" => $today_ss_money / 100,
            "today_ss_num" => $today_ss_num,
            "date_num" => array_values($date_num),
            "date_money" => array_values($date_money),

        ];

        return $this->success($res);
    }

}
