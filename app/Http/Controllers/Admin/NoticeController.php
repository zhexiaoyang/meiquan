<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        if (!in_array($page_size, [10, 20, 30, 50, 60, 70, 80, 100])) {
            $page_size = 10;
        }

        $data = Notice::query()->orderBy("sort")->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'notice' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        Notice::query()->create($request->only("notice", "sort", "status"));

        return $this->success();
    }

    public function destroy(Notice $notice)
    {
        $notice->delete();

        return $this->success();
    }

    public function show(Notice $notice)
    {
        return $this->success($notice);
    }

    public function update(Request $request, Notice $notice)
    {
        $request->validate([
            'notice' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $notice->update($request->only("notice", "sort", "status"));

        return $this->success();
    }
}
