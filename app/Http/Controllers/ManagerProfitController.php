<?php

namespace App\Http\Controllers;

use App\Models\ManagerProfit;
use Illuminate\Http\Request;

class ManagerProfitController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;
        $query = ManagerProfit::with(['shop' => function ($query) {
            $query->select('id', 'shop_name');
        }])->select('id','shop_id','order_no','type','profit','return_type','return_value','description','created_at')
        ->where('user_id', $user_id);
        $running = ManagerProfit::where('type', 1)->where('user_id', $user_id);
        $shopping = ManagerProfit::where('type', 2)->where('user_id', $user_id);

        if ($type = $request->get("type", 1)) {
            $query->where('type', $type);
        }
        if ($sdate = $request->get("sdate", '')) {
            $query->where('created_at', '>=', $sdate);
            $running->where('created_at', '>=', $sdate);
            $shopping->where('created_at', '>=', $sdate);
        }
        if ($edate = $request->get("edate", '')) {
            $query->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));
            $running->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));
            $shopping->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }

        $data = $query->paginate($request->get('page_size', 10));


        $running_total = $running->sum('profit');
        $shopping_total = $shopping->sum('profit');

        $res['page'] = $data->currentPage();
        $res['current_page'] = $data->currentPage();
        $res['total'] = $data->total();
        $res['page_total'] = $data->lastPage();
        $res['last_page'] = $data->lastPage();
        $res['list'] = $data->items();
        $res['running'] = $running_total;
        $res['shopping'] = $shopping_total;
        $res['total_money'] = sprintf("%.2f", $running_total + $shopping_total);

        return $this->success($res);
    }
}
