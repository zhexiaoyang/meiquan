<?php

namespace App\Http\Controllers;

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
}
