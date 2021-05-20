<?php

namespace App\Http\Controllers;

use App\Models\SearchKeyIndex;

class SearchKeyIndexController extends Controller
{
    public function index()
    {
        $search_keys = SearchKeyIndex::select("id", "text")->where("status", 1)->orderBy("sort")->get();

        return $this->success($search_keys);
    }
}
