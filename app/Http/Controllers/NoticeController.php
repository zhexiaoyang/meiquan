<?php

namespace App\Http\Controllers;

use App\Models\Notice;

class NoticeController extends Controller
{
    public function index()
    {
        $notices = Notice::select("id", "notice")->where("status", 1)->orderBy("sort")->get()->pluck("notice");

        return $this->success($notices);
    }
}
