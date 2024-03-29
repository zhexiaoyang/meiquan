<?php

namespace App\Http\Controllers;

use App\Models\CityManager;
use App\Models\User;
use Illuminate\Http\Request;

class CityManagerController extends Controller
{
    public function index()
    {
        $data = User::with('cities')->select("id", "nickname as name")->whereHas('roles', function ($query) {
            $query->where('name', 'city_manager');
        })->where("id", ">", 2400)->where("status", 1)->get();

        return $this->success($data);
    }
}
