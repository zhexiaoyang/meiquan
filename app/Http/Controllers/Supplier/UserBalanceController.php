<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierUserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserBalanceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;

        $user = Auth::user();

        $data = SupplierUserBalance::select("id","money","type","after_money","created_at","description")
            ->where(["user_id" => $user->id])->orderBy("id","desc")->paginate($page_size);

        return $this->page($data);
    }
}
