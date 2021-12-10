<?php

namespace App\Http\Controllers;

use App\Models\ManagerProfit;
use Illuminate\Http\Request;

class ManagerProfitController extends Controller
{
    public function index(Request $request)
    {
        $query = ManagerProfit::query()->select('id','no','type','shop_name','profit','return_type','return_value','description','created_at');

        if ($type = $request->get("type", 1)) {
            $query->where('type', $type);
        }

        $data = $query->paginate($request->get('page_size', 10));

        return $this->page($data);
    }
}
