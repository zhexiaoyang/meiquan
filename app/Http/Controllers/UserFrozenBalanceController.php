<?php

namespace App\Http\Controllers;

use App\Models\UserFrozenBalance;
use Illuminate\Http\Request;

class UserFrozenBalanceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $user = $request->user();

        $data = UserFrozenBalance::where("user_id", $user->id)->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }
}
