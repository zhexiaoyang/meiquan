<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        if (!in_array($page_size, [10, 20, 30, 50, 60, 70, 80, 100])) {
            $page_size = 10;
        }

        $data = Ad::query()->orderBy("sort")->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'image' => 'required',
            'sort' => 'required',
        ]);

        Ad::query()->create($request->only("title", "sort", "image"));

        return $this->success();
    }

    public function destroy(Ad $Ad)
    {
        $Ad->delete();

        return $this->success();
    }

    public function show(Ad $Ad)
    {
        return $this->success($Ad);
    }

    public function update(Request $request, Ad $Ad)
    {
        $request->validate([
            'title' => 'required',
            'sort' => 'required',
            'image' => 'required',
        ]);

        $Ad->update($request->only("title", "sort", "image"));

        return $this->success();
    }
}
