<?php

namespace App\Http\Controllers;

use App\Models\CityManager;
use Illuminate\Http\Request;

class CityManagerController extends Controller
{
    public function index()
    {
        $data = CityManager::select("id", "name", "phone")->get();

        return $this->success($data);
    }
}
