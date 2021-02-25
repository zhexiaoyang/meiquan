<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $pid = intval($request->get("pid", 0));

        $data = Category::query()->select("id","title")->where("pid", $pid)->orderBy("sort", "asc")->get();

        return $this->success($data);
    }
}
