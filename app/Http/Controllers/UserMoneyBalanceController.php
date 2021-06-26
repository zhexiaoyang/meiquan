<?php

namespace App\Http\Controllers;

use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;

class UserMoneyBalanceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $user = $request->user();

        $data = UserMoneyBalance::where("user_id", $user->id)->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }
}
