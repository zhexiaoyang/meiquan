<?php

namespace App\Http\Controllers;

use App\Models\AddressCity;
use App\Models\Shop;
use App\Models\SupplierProduct;
use App\Models\SupplierProductCityPriceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierProductController extends Controller
{
    public function index(Request $request)
    {
        $shop_id = $request->user()->receive_shop_id;
        $page_size = $request->get("page_size", 20);
        $search_key = $request->get("search_key", "");
        $sort = $request->get("sort", "default");

        // 判断是否有收货门店
        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error("没有认证的门店");
        }

        // 查询门店城市编码
        $city_code = AddressCity::query()->where("code", $shop->citycode)->first();

        $query = SupplierProduct::query()->select("id","depot_id","user_id","price","sale_count","is_control","control_price")->whereHas("depot", function(Builder $query) use ($search_key) {
            if ($search_key) {
                $query->where("name", "like", "%{$search_key}%");
            }
        })->with(["depot" => function ($query) {
            $query->select("id","cover","name","spec","unit");
        },"user" => function ($query) {
            $query->select("id","name");
        },"city_price" => function ($query) use ($city_code) {
            $query->select("product_id", "price")->where("city_code", $city_code->id);
        }])->where("status", 20);

        // 筛选城市是否可买
        $query->where(function ($query) use ($city_code) {
            $query->where("sale_type", 1)->orWhereHas("city_price", function(Builder $query) use ($city_code) {
                $query->where("city_code", $city_code->id);
            });
        });

        // 排序
        if ($sort === "sale") {
            $query->orderBy("sale_count", "desc");
        } else if ($sort === "price") {
            $query->orderBy("price");
        }

        $products = $query->paginate($page_size);

        $items = [];

        if (!empty($products)) {
            foreach ($products as $product) {
                $tmp['id'] = $product->id;
                $tmp['is_control'] = $product->is_control;
                $tmp['control_price'] = $product->control_price;
                $tmp['depot_id'] = $product->depot->id;
                $tmp['name'] = $product->depot->name;
                $tmp['cover'] = $product->depot->cover;
                $tmp['spec'] = $product->depot->spec;
                $tmp['unit'] = $product->depot->unit;
                $tmp['sale_count'] = $product->sale_count;
                $tmp['shop_id'] = $product->user->id;
                $tmp['shop_name'] = $product->user->name;
                $tmp['price'] = $product->city_price ? $product->city_price->price : $product->price;
                $items[] = $tmp;
            }
        }

        $result['page'] = $products->currentPage();
        $result['total'] = $products->total();
        $result['current_page'] = $products->currentPage();
        $result['list'] = $items;

        return $this->success($result);
    }

    public function show(SupplierProduct $supplierProduct, Request $request)
    {
        $supplierProduct->load("depot.category");

        $shop_id = $request->user()->receive_shop_id;

        $city_price = null;

        if ($shop = Shop::query()->find($shop_id)) {
            if ($city_code = AddressCity::query()->where("code", $shop->citycode)->first()) {
                $city_price = SupplierProductCityPriceItem::query()->where([
                    "product_id" => $supplierProduct->id,
                    "city_code" => $city_code->id
                ])->first();
            }
        } else {
            return $this->error("没有认证的门店");
        }

        $result = [
            "id" => $supplierProduct->id,
            "number" => $supplierProduct->number,
            "is_control" => $supplierProduct->is_control,
            "control_price" => $supplierProduct->control_price,
            "product_date" => $supplierProduct->product_date,
            "product_end_date" => $supplierProduct->product_end_date,
            "status" => $supplierProduct->status,
            "depot_id" => $supplierProduct->depot_id,
            "stock" => $supplierProduct->stock,
            "price" => $city_price ? $city_price->price : $supplierProduct->price,
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
