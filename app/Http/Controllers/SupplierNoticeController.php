<?php

namespace App\Http\Controllers;

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
}
