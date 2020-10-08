<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Requests\Supplier\ProductRequest;
use App\Http\Requests\Supplier\ProductUpdateRequest;
use App\Models\SupplierCategory;
use App\Models\SupplierDepot;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $page_size = $request->get("page_size", 20);
        $search_key = $request->get("search_key", "");
        $status = $request->get("status", "");
        $stock = intval($request->get("stock", 0));

        $query = SupplierProduct::query()->select("id","depot_id","user_id","price","sale_count","status","stock","product_date","number")
            ->whereHas("depot", function(Builder $query) use ($search_key) {
            if ($search_key) {
                $query->where("name", "like", "%{$search_key}%");
            }
        })->with(["depot" => function ($query) {
            $query->select("id","cover","name","spec","unit","upc","manufacturer","term_of_validity","approval","generi_name");
        }])->where("user_id", $user->id);

        if ($status !== "") {
            $query->where("status", $status);
        }

        if ($stock === 1) {
            $query->where("stock", 0);
        }
        if ($stock === 2) {
            $query->where([
                ["stock", ">", 0],
                ["stock", "<=", 100]
            ]);
        }
        if ($stock === 3) {
            $query->where([
                ["stock", ">", 100],
                ["stock", "<=", 500]
            ]);
        }
        if ($stock === 4) {
            $query->where([
                ["stock", ">", 500],
                ["stock", "<=", 1000]
            ]);
        }
        if ($stock === 5) {
            $query->where("stock", ">",1000);
        }


        $products = $query->orderBy("id", "desc")->paginate($page_size);

        $result = [];

        if (!empty($products)) {
            foreach ($products as $product) {
                $tmp['id'] = $product->id;
                $tmp['price'] = $product->price;
                $tmp['stock'] = $product->stock;
                $tmp['sale_count'] = $product->sale_count;
                $tmp['status'] = $product->status;
                $tmp['number'] = $product->number;
                $tmp['product_date'] = $product->product_date;
                $tmp['cover'] = $product->depot->cover;
                $tmp['upc'] = $product->depot->upc;
                $tmp['name'] = $product->depot->name;
                $tmp['spec'] = $product->depot->spec;
                $tmp['unit'] = $product->depot->unit;
                $tmp['manufacturer'] = $product->depot->manufacturer;
                $tmp['approval'] = $product->depot->approval;
                $tmp['term_of_validity'] = $product->depot->term_of_validity;
                $result[] = $tmp;
            }
        }

        return $this->page($products, $result);
    }

    public function depot(Request $request)
    {
        $page_size = $request->get("page_size", 20);
        $upc = $request->get("upc", "");
        $search_key = $request->get("search_key", "");

        $query = SupplierDepot::query()->select("id","cover","name","spec","unit","upc","status","manufacturer","approval","term_of_validity");

        if ($search_key) {
            $query->where("name", "like", "%{$search_key}%")
                ->orWhere("generi_name", "like", "%{$search_key}%")
                ->orWhere("upc", "like", "%{$search_key}%")
                ->orWhere("manufacturer", "like", "%{$search_key}%")
                ->orWhere("approval", "like", "%{$search_key}%");
        }

//        if ($upc) {
//            $query->where("upc", "like", "%{$upc}%");
//        }

        $depots = $query->orderBy("id", "desc")->paginate($page_size);


        return $this->page($depots);
    }

    /**
     * 商品详情
     * @param Request $request
     * @return mixed
     */
    public function show(Request $request)
    {
        if (!$id = $request->get("id", 0)) {
            return $this->error("药品不存在");
        }

        $user = Auth::user();

        $product = SupplierProduct::with(["depot.category"])->where("user_id", $user->id)->find($id);

        // return $product;

        if (!$product) {
            return $this->error("数据不存在");
        }

        $result = [
            "id" => $product->id,
            "status" => $product->status,
            "depot_id" => $product->depot_id,
            "stock" => $product->stock,
            "price" => $product->price,
            "category_id" => $product->depot->category_id,
            "name" => $product->depot->name,
            "spec" => $product->depot->spec,
            "unit" => $product->depot->unit,
            "is_otc" => $product->depot->is_otc,
            "description" => $product->depot->description,
            "upc" => $product->depot->upc,
            "approval" => $product->depot->approval,
            "cover" => $product->depot->cover,
            "images" => explode(",", $product->depot->images),
            "generi_name" => $product->depot->generi_name,
            "manufacturer" => $product->depot->manufacturer
        ];

        if (!$product->depot->description) {
            $result['yfyl'] = $product->depot->yfyl;
            $result['syz'] = $product->depot->syz;
            $result['syrq'] = $product->depot->syrq;
            $result['cf'] = $product->depot->cf;
            $result['blfy'] = $product->depot->blfy;
            $result['jj'] = $product->depot->jj;
            $result['zysx'] = $product->depot->zysx;
            $result['ypxhzy'] = $product->depot->ypxhzy;
            $result['xz'] = $product->depot->xz;
            $result['bz'] = $product->depot->bz;
            $result['jx'] = $product->depot->jx;
            $result['zc'] = $product->depot->zc;
        }

        return $this->success($result);
    }

    /**
     * 添加商品
     * @param ProductRequest $request
     * @return mixed
     */
    public function store(ProductRequest $request)
    {
        $user = Auth::user();

        $depot_data = $request->only("name","spec","unit","is_otc","upc","approval","cover","category_id","price","term_of_validity","manufacturer","generi_name");

        $depot_data['images'] = implode(",", $request->get("images"));

        $product_data['user_id'] = $user->id;
        $product_data['price'] = $request->get("price");
        $product_data['stock'] = $request->get("stock");
        $product_data['number'] = $request->get("number") ?? "";

        try {
            DB::transaction(function () use ($depot_data, $product_data) {
                $depot = SupplierDepot::query()->create($depot_data);

                $product_data['depot_id'] = $depot->id;

                SupplierProduct::query()->create($product_data);
            });
        } catch (\Exception $e) {
            \Log::error('添加药品失败', [$e->getMessage()]);
            return $this->error("添加失败");
        }

        return $this->success();
    }

    /**
     * 更新商品
     * @param ProductUpdateRequest $request
     * @return mixed
     */
    public function update(ProductUpdateRequest $request)
    {
        $data = $request->only("stock","price", "status","number","product_date");

        if (!$product = SupplierProduct::query()->find($request->get("id"))) {
            return $this->error("药品不存在");
        }

        $product->update($data);

        return $this->success();
    }

    /**
     * 上传商品
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function destroy(Request $request)
    {
        if (!$product = SupplierProduct::query()->find($request->get("id"))) {
            return $this->error("数据不存在");
        }

        $product->delete();

        return $this->success();
    }

    /**
     * 上下架修改
     * @param Request $request
     * @return mixed
     */
    public function online(Request $request)
    {
        if (!$product = SupplierProduct::query()->find($request->get("id"))) {
            return $this->error("数据不存在");
        }

        $product->status = $product->status === 10 ? 20 : 10;
        $product->save();

        return $this->success();
    }

    /**
     * 品库中添加商品
     * @param Request $request
     * @return mixed
     */
    public function add(Request $request)
    {
        if (!$depot = SupplierDepot::query()->find($request->get("depot_id", 0))) {
            return $this->error("品库中无此商品");
        }

        if ($depot->status !== 1) {
            return $this->error("商品正在审核中，请稍后");
        }

        $user = Auth::user();

        if ($product = SupplierProduct::query()->where(['depot_id' => $depot->id, "user_id" => $user->id])->first()) {
            return $this->error("商品已存在，不能重复添加");
        }

        $request->validate([
            'price' => 'bail|required|numeric|min:0.01',
            'stock' => 'bail|required|numeric',
            'number' => 'bail|required',
            'product_date' => 'bail|required|date',
        ],[
            'price.required' => '药品价格不能为空',
            'price.numeric' => '药品价格格式不正确',
            'price.min' => '药品价格不能小于等于0',
            'stock.required' => '药品库存不能为空',
            'stock.numeric' => '药品库存格式不正确',
            'spec.required' => '药品规格不能为空',
            'number.required' => '批号不能为空',
            'product_date.required' => '生产日期不能为空',
        ]);

        $product_data['user_id'] = $user->id;
        $product_data['depot_id'] = $depot->id;
        $product_data['status'] = 20;
        $product_data['price'] = $request->get("price");
        $product_data['stock'] = $request->get("stock");
        $product_data['number'] = $request->get("number");
        $product_data['product_date'] = $request->get("product_date");
        SupplierProduct::query()->create($product_data);

        return $this->success();
    }

    public function category()
    {
        $result = [];

        $categories = SupplierCategory::query()->select("id","parent_id","title")->where("status", 1)
            ->orderBy("parent_id")->get()->toArray();

        if (!empty($categories)) {
            foreach ($categories as $category) {
                if ($category['parent_id'] === 0) {
                    $category['children'] = [];
                    $result[$category['id']] = $category;
                } else {
                    if (isset($result[$category['parent_id']])) {
                        $result[$category['parent_id']]['children'][] = $category;
                    }
                }
            }
        }

        return $this->success(array_values($result));
    }
}
