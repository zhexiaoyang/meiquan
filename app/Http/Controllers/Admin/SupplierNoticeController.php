<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierNotice;
use Illuminate\Http\Request;

class SupplierNoticeController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $notices = SupplierNotice::where("status", 1)->orderBy("id", "desc")->paginate($page_size);

        return $this->page($notices);
    }

    public function show(SupplierNotice $notice)
    {
        if ($notice->status === 0) {
            return $this->error("资源不存在");
        }

        return $this->success($notice);
    }

    public function store(Request $request)
    {
        if (!$title = $request->get("title", "")) {
            return $this->error("标题不能为空");
        }
        if (!$content = $request->get("content", "")) {
            return $this->error("内容不能为空");
        }

        SupplierNotice::query()->create(["title" => $title, "content" => $content]);

        return $this->success();
    }

    public function update(SupplierNotice $notice, Request $request)
    {
        if ($title = $request->get("title", "")) {
            $notice->title = $title;
        }
        if ($content = $request->get("content", "")) {
            $notice->content = $content;
        }

        $notice->save();

        return $this->success();
    }

    public function destroy(SupplierNotice $notice)
    {
        $notice->delete();
        return $this->success();
    }
}
