<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchKey;
use Illuminate\Http\Request;

class SearchKeyController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        if (!in_array($page_size, [10, 20, 30, 50, 60, 70, 80, 100])) {
            $page_size = 10;
        }

        $data = SearchKey::query()->orderBy("sort")->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        SearchKey::query()->create($request->only("text", "sort", "status"));

        return $this->success();
    }

    public function destroy(SearchKey $searchKey)
    {
        $searchKey->delete();

        return $this->success();
    }

    public function show(SearchKey $searchKey)
    {
        return $this->success($searchKey);
    }

    public function update(Request $request, SearchKey $searchKey)
    {
        $request->validate([
            'text' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $searchKey->update($request->only("text", "sort", "status"));

        return $this->success();
    }
}
