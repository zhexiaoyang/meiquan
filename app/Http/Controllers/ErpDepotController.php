<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ErpDepot;
use Illuminate\Http\Request;

class ErpDepotController extends Controller
{
    public function index(Request $request)
    {
        $upc = $request->get("upc", "");
        $name = $request->get("name", "");
        $first_code = $request->get("first_code", 0);
        $second_code = $request->get("second_code", 0);
        $page_size = $request->get("page_size");

        $query = ErpDepot::query();

        if ($upc) {
            $query->where("upc", "like", "%{$upc}%");
        }

        if ($name) {
            $query->where("name", "like", "%{$name}%");
        }

        if ($first_code) {
            $query->where("first_code", $first_code);
        }

        if ($second_code) {
            $query->where("second_code", $second_code);
        }

        $data = $query->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        $upc = $request->get("upc", "");
        $name = $request->get("name", "");
        $first_code = $request->get("first_code", 0);
        $second_code = $request->get("second_code", 0);

        if (!$name) {
            return $this->error("药品名称不能为空");
        }

        if (!$upc) {
            return $this->error("药品条码不能为空");
        }

        if (!$first = Category::find($first_code)) {
            return $this->error("一级分类不存在");
        }

        if (!$second = Category::find($second_code)) {
            return $this->error("二级分类不存在");
        }

        ErpDepot::create([
            "name" => $name,
            "upc" => $upc,
            "c1" => $first->title,
            "first_code" => $first_code,
            "c2" => $second->title,
            "second_code" => $second_code,
        ]);

        return $this->success();
    }

    public function update(Request $request)
    {
        $id = $request->get("id", 0);
        $upc = $request->get("upc", "");
        $name = $request->get("name", "");
        $first_code = $request->get("first_code", 0);
        $second_code = $request->get("second_code", 0);

        if (!$depot = ErpDepot::find($id)) {
            return $this->error("商品不存在");
        }

        if (!$name) {
            return $this->error("药品名称不能为空");
        }

        if (!$upc) {
            return $this->error("药品条码不能为空");
        }

        if (!$first = Category::find($first_code)) {
            return $this->error("一级分类不存在");
        }

        if (!$second = Category::find($second_code)) {
            return $this->error("二级分类不存在");
        }

        $depot->name = $name;
        $depot->upc = $upc;
        $depot->c1 = $first->title;
        $depot->first_code = $first_code;
        $depot->c2 = $second->title;
        $depot->second_code = $second_code;
        $depot->save();

        return $this->success();
    }

    public function category()
    {
        $result = [];

        $categories = Category::orderBy("pid")->get();

        if (!empty($categories)) {
            foreach ($categories as $category) {
                if ($category->pid === 0) {
                    $result[$category->id] = $category->toArray();
                } else {
                    $result[$category->pid]['children'][] = $category->toArray();
                }
            }
        }

        return $this->success(array_values($result));
    }
}
