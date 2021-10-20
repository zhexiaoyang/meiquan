<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnlineShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnlineStatisticController extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->get("start_date", date("Y-m-") . '1');
        $end_date = $request->get("end_date", date("Y-m-d", time() - 86400));
        $res = [
            'status0' => 0,
            'status20' => 0,
            'status40' => 0,
        ];

        if (!$start_date || !$end_date) {
            return $this->success($res);
        }

        $query = OnlineShop::query()->select("status", DB::raw('count(status) as status_count'))->where("created_at", ">", $start_date)
            ->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));

        // 判断可以查询的药店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $statuses = $query->groupBy("status")->get();

        if (!empty($statuses)) {
            foreach ($statuses as $status) {
                if ($status['status'] === 0) {
                    $res['status0'] += $status['status_count'];
                }
                if ($status['status'] === 10) {
                    $res['status0'] += $status['status_count'];
                }
                if ($status['status'] === 20) {
                    $res['status20'] = $status['status_count'];
                }
                if ($status['status'] === 40) {
                    $res['status40'] = $status['status_count'];
                }
            }
        }

        return $this->success($res);
    }
}
