<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Models\SupplierOrder;
use App\Models\SupplierUser;
use App\Models\User;
use Illuminate\Http\Request;

class FundController extends Controller
{
    /**
     * 已认证门店列表
     */
    public function shops()
    {
        $shops = Shop::select("id", "shop_name")->where("auth", 10)->orderBy("id")->get();

        return $this->success($shops);
    }

    /**
     * 已认证供货商列表
     */
    public function supplier()
    {
        $shops = SupplierUser::select("id", "name")->where("is_auth", 1)->orderBy("id")->get();

        return $this->success($shops);
    }

    public function running_orders(Request $request)
    {

        $page_size = intval($request->get("page_size", 10)) ?: 10;
        $shop_id = intval($request->get("shop_id", 0));

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

        if ($shop_id) {
            $where[] = ["shop_id", $shop_id];
        }

        $orders = Order::with(['shop', 'deduction'])->where($where)->orderByDesc("over_at")->paginate($page_size);

        $data = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $data[] = [
                    'id' => $order->id,
                    'shop_id' => $order->shop_id,
                    'shop_name' => $order->shop->shop_name,
                    'order_id' => $order->order_id,
                    'money' => $order->money,
                    'cancel_fee' => $order->deduction,
                    'profit' => $order->profit,
                    'over_at' => $order->over_at,
                    'expenditure' => sprintf("%.2f", $order->money - $order->profit)
                ];
            }
        }

        // $res['total_profit'] = $total_profit;
        // $res['avg_profit'] = sprintf("%.2f", $total_profit / $day);
        // $res['total_money'] = $total_money;
        // $res['avg_money'] = sprintf("%.2f", $total_money / $day);
        // $res['total_number'] = $total_number;
        // $res['avg_number'] = intval($total_number / $day);
        $res['page'] = $orders->currentPage();
        $res['total'] = $orders->total();
        $res['list'] = $data;

        return $this->success($res);
    }

    public function shopping_orders(Request $request)
    {

        $page_size = intval($request->get("page_size", 10)) ?: 10;
        $shop_id = intval($request->get("shop_id", 0));

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
            ["completion_at", '>', $start_date],
            ["completion_at", '<', $end_date],
        ];

        if ($shop_id) {
            $where[] = ["shop_id", $shop_id];
        }

        $orders = SupplierOrder::with(['shop'])->where($where)->orderByDesc("completion_at")->paginate($page_size);

        $data = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                // 结算金额（js有精度问题，放到程序里面做）
                $profit_fee = $order->total_fee - $order->mq_charge_fee;
                if ($order->payment_method !==0 && $order->payment_method !== 30) {
                    $profit_fee -= $order->pay_charge_fee;
                } else {
                    $order_info['pay_charge_fee'] = 0;
                }

                $data[] = [
                    'id' => $order->id,
                    'shop_id' => $order->shop_id,
                    'shop_name' => $order->shop->name,
                    'order_id' => $order->no,
                    'total_fee' => $order->total_fee,
                    'completion_at' => $order->completion_at,
                    'profit_fee' => (float) sprintf("%.2f",$profit_fee),
                    'mq_charge_fee' => $order->mq_charge_fee,
                ];
            }
        }

        $res['page'] = $orders->currentPage();
        $res['total'] = $orders->total();
        $res['list'] = $data;

        return $this->success($res);
    }

    public function statistic(Request $request)
    {

        $total_income = 0;
        $total_income_running = 0;
        $total_income_shop = 0;
        $total_expenditure = 0;
        $total_expenditure_running = 0;
        $total_expenditure_shop = 0;

        if (!$start_date = $request->get("start_date")) {
            return $this->error("开始日期不能为空");
        }
        if (!$end_date = $request->get("end_date")) {
            return $this->error("结束日期不能为空");
        }

        $end_time = strtotime($end_date);
        $end_date = date("Y-m-d", $end_time + 86400);

        $where_running = [
            ["status", 70],
            ["over_at", '>', $start_date],
            ["over_at", '<', $end_date],
        ];

        $where_shopping = [
            ["status", 70],
            ["completion_at", '>', $start_date],
            ["completion_at", '<', $end_date],
        ];

        $running_orders = Order::select("id","money","profit")->where($where_running)->get();
        $shopping_orders = SupplierOrder::select("id","total_fee","mq_charge_fee","pay_charge_fee","payment_method")->where($where_shopping)->get();

        // 收入
        if (!empty($running_orders)) {
            // 跑腿订单收入
            foreach ($running_orders as $running_order) {
                $total_income += $running_order->money;
                $total_income_running += $running_order->money;
            }
        }
        if (!empty($shopping_orders)) {
            // 商城订单收入
            foreach ($shopping_orders as $shopping_order) {
                $total_income += $shopping_order->total_fee;
                $total_income_shop += $shopping_order->total_fee;
            }
        }
        $user_running = User::sum("money");
        $user_shopping = User::sum("frozen_money");
        $user_operate = User::sum("operate_money");

        // 支出
        if (!empty($running_orders)) {
            // 跑腿订单支出
            foreach ($running_orders as $running_order) {
                $total_expenditure += $running_order->money - $running_order->profit;
                $total_expenditure_running += $running_order->money - $running_order->profit;
            }
        }
        if (!empty($shopping_orders)) {
            // 商城订单支出
            foreach ($shopping_orders as $shopping_order) {
                // 结算金额
                $profit_fee = $shopping_order->total_fee - $shopping_order->mq_charge_fee;
                if ($shopping_order->payment_method !==0 && $shopping_order->payment_method !== 30) {
                    $profit_fee -= $shopping_order->pay_charge_fee;
                }
                $total_expenditure += $profit_fee;
                $total_expenditure_running += $profit_fee;
            }
        }



        $res = [
            'user_running' => sprintf("%.2f", $user_running),
            'user_shopping' => sprintf("%.2f", $user_shopping),
            'user_operate' => sprintf("%.2f", $user_operate),
            'total_income' => sprintf("%.2f", $total_income + $user_running + $user_shopping),
            'total_income_running' => sprintf("%.2f", $total_income_running + $user_running),
            'total_income_shop' => sprintf("%.2f", $total_income_shop + $user_shopping),
            'total_expenditure' => sprintf("%.2f", $total_expenditure),
            'total_expenditure_running' => sprintf("%.2f", $total_expenditure_running),
            'total_expenditure_shop' => sprintf("%.2f", $total_expenditure_shop),
            'total' => sprintf("%.2f", ($total_income + $user_running + $user_shopping) - ($total_expenditure)),
        ];

        return $this->success($res);
    }
}
