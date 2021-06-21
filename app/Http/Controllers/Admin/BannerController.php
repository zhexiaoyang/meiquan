<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        if (!in_array($page_size, [10,20,30,40,50,60,70,80,90,100])) {
            $page_size = 10;
        }

        $banners = Banner::query()->orderBy("sort")->paginate($page_size);

        return $this->page($banners);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'image' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        Banner::query()->create($request->only("title", "image", "sort", "status"));

        return $this->success();
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return $this->success();
    }

    public function show(Banner $banner)
    {
        return $this->success($banner);
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'title' => 'required',
            'image' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $banner->update($request->only("title", "image", "sort", "status"));

        return $this->success();
    }
}
