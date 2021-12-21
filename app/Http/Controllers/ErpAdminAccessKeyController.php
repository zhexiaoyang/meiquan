<?php

namespace App\Http\Controllers;

use App\Models\ErpAccessKey;
use Illuminate\Http\Request;

class ErpAdminAccessKeyController extends Controller
{
    public function index(Request $request)
    {
        $search_key = $request->get("search_key", "");
        $page_size = $request->get("page_size");

        $query = ErpAccessKey::where("status", 1);

        if ($search_key) {
            $query->where("title", "like", "%{$search_key}%");
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function info(Request $request)
    {
        if (!$id = $request->get("access_id")) {
            return $this->error("密钥不存在");
        }

        if (!$access_key = ErpAccessKey::query()->select("id","title","description","created_at")->find($id)) {
            return $this->error("密钥不存在");
        }

        return $this->success($access_key);
    }

    public function store(Request $request)
    {
        if (!$title = $request->get("title")) {
            return $this->error("标题不能为空");
        }

        $description = $request->get("description", "");

        ErpAccessKey::create(["title" => $title, "description" => $description]);

        return $this->success();
    }

    public function update(Request $request)
    {
        if (!$id = $request->get("id")) {
            return $this->error("密钥不存在");
        }

        if (!$title = $request->get("title")) {
            return $this->error("标题不能为空");
        }

        if (!$access_key = ErpAccessKey::query()->find($id)) {
            return $this->error("密钥不存在");
        }

        $description = $request->get("description", "");

        $access_key->title = $title;
        $access_key->description = $description;
        $access_key->save();

        return $this->success();
    }

    public function destroy(Request $request)
    {
        if (!$access = ErpAccessKey::find($request->get("id", 0))) {
            return $this->error("令牌不存在");
        }

        $access->status = 0;
        $access->save();

        return $this->success();
    }
}
