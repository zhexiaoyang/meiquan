<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchKeyIndex;
use Illuminate\Http\Request;

class SearchKeyIndexController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        if (!in_array($page_size, [10, 20, 30, 50, 60, 70, 80, 100])) {
            $page_size = 10;
        }

        $data = SearchKeyIndex::query()->orderBy("sort")->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        SearchKeyIndex::query()->create($request->only("text", "sort", "status"));

        return $this->success();
    }

    public function destroy(SearchKeyIndex $searchKeyIndex)
    {
        $searchKeyIndex->delete();

        return $this->success();
    }

    public function show(SearchKeyIndex $searchKeyIndex)
    {
        return $this->success($searchKeyIndex);
    }

    public function update(Request $request, SearchKeyIndex $searchKeyIndex)
    {
        $request->validate([
            'text' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $searchKeyIndex->update($request->only("text", "sort", "status"));

        return $this->success();
    }
}
