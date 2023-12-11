<?php

namespace App\Http\Controllers;

use App\Exports\MoneyBalanceExport;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;

class UserMoneyBalanceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $type = intval($request->get("type", 0));
        $day = intval($request->get("day", 0));
        $shop_id = intval($request->get("shop_id", 0));
        $start_date = $request->get("start_date", "");
        $end_date = $request->get("end_date", "");
        $user = $request->user();


        $query = UserMoneyBalance::where("user_id", $user->id);

        if (in_array($type, [1, 2])) {
            $query->where("type", $type);
        }

        if ($shop_id) {
            $query->where("shop_id", $shop_id);
        }

        if ($day === 1) {
            $query->where("created_at", '>=', date("Y-m-d"));
        }

        if ($day === 2) {
            $query->where("created_at", '>=', date("Y-m-d", time() - 86400));
        }

        if ($day === 3) {
            $query->where("created_at", '>=', date("Y-m-d", time() - 86400 * 6));
        }

        if ($day === 4) {
            $query->where("created_at", '>=', date("Y-m-d", time() - 86400 * 30));
        }

        if ($day === 5 && $start_date !== "" && $end_date !== "") {
            $query->where("created_at", '>=', date("Y-m-d", strtotime($start_date)));
            $query->where("created_at", '<', date("Y-m-d", strtotime($end_date) + 86400));
        }

        $data = $query->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }

    public function export(Request $request, MoneyBalanceExport $balanceExport)
    {
        return $balanceExport->withRequest($request);
    }
}
