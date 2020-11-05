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
        $shop_id = $request->get("shop_id", 0);
        $search_key = $request->get("search_key", "");
        $sort = $request->get("sort", "default");

        $query = SupplierProduct::query()->select("id","depot_id","user_id","price","sale_count")->whereHas("depot", function(Builder $query) use ($search_key) {
            if ($search_key) {
                $query->where("name", "like", "%{$search_key}%");
            }
        })->with(["depot" => function ($query) {
            $query->select("id","cover","name","spec","unit");
        },"user" => function ($query) {
            $query->select("id","name");
        }])->where("status", 20);

        if ($sort === "sale") {
            $query->orderBy("sale_count", "desc");
        } else if ($sort === "price") {
            $query->orderBy("price");
        }


        $products = $query->paginate($page_size);

        return $this->page($products);
    }

    public function show(SupplierProduct $supplierProduct)
    {
        $supplierProduct->load("depot.category");

        $result = [
            "id" => $supplierProduct->id,
            "status" => $supplierProduct->status,
            "depot_id" => $supplierProduct->depot_id,
            "stock" => $supplierProduct->stock,
            "price" => $supplierProduct->price,
            "detail" => $supplierProduct->detail,
            "category_id" => $supplierProduct->depot->category_id,
            "name" => $supplierProduct->depot->name,
            "spec" => $supplierProduct->depot->spec,
            "unit" => $supplierProduct->depot->unit,
            "is_otc" => $supplierProduct->depot->is_otc,
            "description" => $supplierProduct->depot->description,
            "upc" => $supplierProduct->depot->upc,
            "approval" => $supplierProduct->depot->approval,
            "cover" => $supplierProduct->depot->cover,
            "images" => explode(",", $supplierProduct->depot->images),
            "generi_name" => $supplierProduct->depot->generi_name,
            "manufacturer" => $supplierProduct->depot->manufacturer
        ];

        if (!$supplierProduct->depot->description) {
            $result['yfyl'] = $supplierProduct->depot->yfyl;
            $result['syz'] = $supplierProduct->depot->syz;
            $result['syrq'] = $supplierProduct->depot->syrq;
            $result['cf'] = $supplierProduct->depot->cf;
            $result['blfy'] = $supplierProduct->depot->blfy;
            $result['jj'] = $supplierProduct->depot->jj;
            $result['zysx'] = $supplierProduct->depot->zysx;
            $result['ypxhzy'] = $supplierProduct->depot->ypxhzy;
            $result['xz'] = $supplierProduct->depot->xz;
            $result['bz'] = $supplierProduct->depot->bz;
            $result['jx'] = $supplierProduct->depot->jx;
            $result['zc'] = $supplierProduct->depot->zc;
        }

        return $this->success($result);
    }
}
