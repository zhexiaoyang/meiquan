<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $data = [];
        $categories = Category::query()->orderBy("pid")->orderBy("sort")->get();

        if (!empty($categories)) {
            foreach ($categories as $category) {
                $_tmp = [
                    'id' => $category->id,
                    'pid' => $category->pid,
                    'title' => $category->title,
                    'picture' => $category->picture,
                    'sort' => $category->sort,
                    'status' => (bool) $category->status,
                    'created_at' => date("Y-m-d", strtotime($category->created_at)),
                    'updated_at' => date("Y-m-d", strtotime($category->updated_at)),
                ];
                if ($category->pid === 0) {
                    $data[$category->id] = $_tmp;
                } else {
                    $data[$category->pid]['children'][] = $_tmp;
                }
            }
        }

        return $this->success(array_values($data));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pid' => 'required',
            'title' => 'required',
            'picture' => 'required',
            'sort' => 'required',
        ]);

        Category::query()->create($request->only("pid", "title", "picture", "sort"));

        return $this->success();
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return $this->success();
    }

    public function show(Category $category)
    {
        return $this->success($category);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'pid' => 'required',
            'title' => 'required',
            'picture' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $category->update($request->only("pid", "title", "picture", "sort", "status"));

        return $this->success();
    }
}
