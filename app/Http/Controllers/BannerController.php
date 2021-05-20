<?php

namespace App\Http\Controllers;

use App\Models\Banner;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::select("id", "title", "image")->where("status", 1)->orderBy("sort")->get();

        return $this->success($banners);
    }
}
