<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SupplierCategory;
use Illuminate\Http\Request;

class SupplierCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = SupplierCategory::query()->select("id","title")->where(
            [
                "parent_id" => 0,
                "status" => 1
            ]
        )->get();

        return $this->success($categories);
    }

    public function all()
    {
        $categories = [];

        $data = Category::select("id","pid", "title", "picture")->where("status", 1)
            ->orderBy("pid")->orderBy("sort")->get()->toArray();

        if (!empty($data)) {
            foreach ($data as $v) {
                if ($v['pid'] === 0) {
                    $categories[$v['id']] = $v;
                } else {
                    $categories[$v['pid']]['children'][] = $v;
                }
            }
        }

        return $this->success(array_values($categories));
    }
}
