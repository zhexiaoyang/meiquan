<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DepositExport;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $phone = $request->get('phone', '');
        $start_date = $request->get('start_date', '');
        $end_date = $request->get('end_date', '');
        $type = $request->get("type", 1);

        $query = Deposit::with(['user' => function ($query) {
            $query->with(["my_shops" => function ($query) {
                $query->select("id", "own_id", "shop_name");
            }]);
            $query->select("id", "phone", "name");
        }])->select("id", "user_id", "amount", "paid_at", "pay_method", "pay_no", "type", "created_at");

        if ($phone) {
            $query->whereHas("user", function ($query) use ($phone) {
                $query->where('phone', 'like', "%{$phone}%");
            });
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $data = $query->where("type", $type)->where("status", 1)->orderByDesc("id")->paginate($page_size);

        return $this->page($data);
    }

    public function export(Request $request, DepositExport $depositExport)
    {
        // $start_date = $request->get('start_date', '');
        // $end_date = $request->get('end_date', '');
        //
        // if (!$start_date) {
        //     return $this->error("请选择起始时间");
        // }
        //
        // if (!$end_date) {
        //     return $this->error("请选择结束时间");
        // }
        //
        // if ((strtotime($end_date) - strtotime($start_date)) / 86400 > 31) {
        //     return $this->error("最多能导出一个月充值记录");
        // }
        return $depositExport->withRequest($request);
    }
}
