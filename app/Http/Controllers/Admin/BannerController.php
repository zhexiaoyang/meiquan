<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * 列表
     */
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        if (!in_array($page_size, [10,20,30,40,50,60,70,80,90,100])) {
            $page_size = 10;
        }

        $banners = Banner::query()->orderBy("sort")->paginate($page_size);

        return $this->page($banners);
    }

    /**
     * 添加
     */
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

    /**
     * 删除
     */
    public function destroy(Banner $banner)
    {
        $banner->delete();

        return $this->success();
    }

    /**
     * 详情
     */
    public function show(Banner $banner)
    {
        return $this->success($banner);
    }

    /**
     * 更新
     */
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
