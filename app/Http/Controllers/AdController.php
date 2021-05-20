<?php

namespace App\Http\Controllers;

use App\Models\Ad;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::select("id", "title", "image")->where("status", 1)->orderBy("sort")->get();

        return $this->success($ads);
    }
}
