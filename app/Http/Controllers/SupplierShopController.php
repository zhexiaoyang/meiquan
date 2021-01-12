<?php

namespace App\Http\Controllers;

use App\Models\AddressCity;
use App\Models\Shop;
use App\Models\SupplierProduct;
use App\Models\SupplierUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierShopController extends Controller
{
    public function show(Request $request)
    {
        $shop_id = $request->get("shop_id", 0);

        $shop = SupplierUser::select("id","avatar","name","telephone","description","notice")->find($shop_id);

        if (!$shop) {
            return $this->error("供货商不存在");
        }

        return $this->success($shop);
    }

    public function productList(Request $request)
    {
        $supplier_id = $request->get("shop_id", 0);
        $shop_id = $request->user()->receive_shop_id;
        $page_size = $request->get("page_size", 20);
        $search_key = $request->get("search_key", "");
        $sort = $request->get("sort", "default");

        if (!$supplier_shop = SupplierUser::query()->find($supplier_id)) {
            return $this->error("供货商不存在");
        }

        // 判断是否有收货门店
        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error("没有认证的门店");
        }

        // 查询门店城市编码
        $city_code = AddressCity::query()->where("code", $shop->citycode)->first();

        $query = SupplierProduct::query()->select("id","depot_id","user_id","price","sale_count","is_control","is_active","control_price")->whereHas("depot", function(Builder $query) use ($search_key) {
            if ($search_key) {
                $query->where("name", "like", "%{$search_key}%");
            }
        })->with(["depot" => function ($query) {
            $query->select("id","cover","name","spec","unit");
        },"user" => function ($query) {
            $query->select("id","name");
        },"city_price" => function ($query) use ($city_code) {
            $query->select("product_id", "price")->where("city_code", $city_code->id);
        }]);

        $query = $query->where("status", 20)->where("user_id", $supplier_id);

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

        $query->orderBy("sort_admin")->orderBy("sort_supplier");

        $products = $query->paginate($page_size);

        $items = [];

        if (!empty($products)) {
            foreach ($products as $product) {
                $tmp['id'] = $product->id;
                $tmp['is_active'] = $product->is_active;
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
}
