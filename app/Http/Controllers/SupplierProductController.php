<?php

namespace App\Http\Controllers;

use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierProductController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 20);
        $search_key = $request->get("search_key", "");

        $query = SupplierProduct::query()->select("id","depot_id","user_id","price")->whereHas("depot", function(Builder $query) use ($search_key) {
            if ($search_key) {
                $query->where("name", "like", "%{$search_key}%");
            }
        })->with(["depot" => function ($query) {
            $query->select("id","cover","name","spec","unit");
        },"user" => function ($query) {
            $query->select("id","name");
        }]);


        $products = $query->paginate($page_size);

        return $this->page($products);
    }

    public function show(SupplierProduct $supplierProduct)
    {
        $supplierProduct->load("depot");

        return $this->success($supplierProduct);
    }
}
