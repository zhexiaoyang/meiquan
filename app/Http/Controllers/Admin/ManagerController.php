<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::select('id', 'nickname')->whereHas('roles', function ($query)  {
            $query->where('name', 'city_manager');
        });

        if ($name = $request->get('name')) {
            $query->where('nickname', 'like', "{$name}");
        }

        $user = $query->where('status', 1)->where('id', '>', 2000)->get();

        return $this->success($user);
    }
}
