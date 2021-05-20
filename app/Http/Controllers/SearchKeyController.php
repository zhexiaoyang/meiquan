<?php

namespace App\Http\Controllers;

use App\Models\SearchKey;

class SearchKeyController extends Controller
{
    public function index()
    {
        $search_keys = SearchKey::select("id", "text")->where("status", 1)->orderBy("sort")->get();

        return $this->success($search_keys);
    }
}
