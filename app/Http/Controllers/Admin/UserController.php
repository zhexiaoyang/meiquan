<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;

class UserController extends Controller
{
    public function statistics()
    {
        $running_total = User::query()->sum("money");
        $shopping_total = User::query()->sum("frozen_money");
        $running_number = User::query()->where('money', '>', 0)->count();
        $shopping_number = User::query()->where('frozen_money', '>', 0)->count();

        $where_t = [
            ['status', 1],
            ['paid_at', '>=', date("Y-m-d")],
            ['paid_at', '<', date("Y-m-d",strtotime("+1 day"))],
        ];
        $where_y = [
            ['status', 1],
            ['paid_at', '>=', date("Y-m-d",strtotime("-1 day"))],
            ['paid_at', '<', date("Y-m-d")],
        ];
        $today_running = Deposit::query()->where($where_t)->where("type", 1)->sum('amount');
        $today_shopping = Deposit::query()->where($where_t)->where("type", 2)->sum('amount');
        $yesterday_running = Deposit::query()->where($where_y)->where("type", 1)->sum('amount');
        $yesterday_shopping = Deposit::query()->where($where_y)->where("type", 2)->sum('amount');

        $data = [
            'running_total' => $running_total,
            'shopping_total' => $shopping_total,
            'running_number' => $running_number,
            'shopping_number' => $shopping_number,
            'today_running' => $today_running,
            'today_shopping' => $today_shopping,
            'yesterday_running' => $yesterday_running,
            'yesterday_shopping' => $yesterday_shopping,
        ];

        return $this->success($data);
    }
}
