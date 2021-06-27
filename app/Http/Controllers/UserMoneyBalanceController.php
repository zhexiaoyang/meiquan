<?php

namespace App\Http\Controllers;

use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;

class UserMoneyBalanceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $type = $request->get("type", 0);
        $user = $request->user();

        $query = UserMoneyBalance::where("user_id", $user->id);

        if (in_array($type, [1, 2])) {
            $query->where("type", $type);
        }

        $data = $query->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }
}
