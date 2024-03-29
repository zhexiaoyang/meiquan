<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Requests\Supplier\ProductRequest;
use App\Http\Requests\Supplier\ProductUpdateRequest;
use App\Models\Category;
use App\Models\MedicineDepot;
use App\Models\SupplierCategory;
use App\Models\SupplierDepot;
use App\Models\SupplierProduct;
use App\Models\SupplierProductCityPrice;
use App\Models\SupplierProductCityPriceItem;
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

        $query = SupplierProduct::query()->select("id","depot_id","third_id","user_id","price","is_control","is_meituan","is_ele","control_price","sale_count","status","stock","sale_type","product_date","product_end_date","number","weight","detail")
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
                $tmp['third_id'] = $product->third_id;
                $tmp['is_control'] = $product->is_control;
                $tmp['is_meituan'] = $product->is_meituan;
                $tmp['is_ele'] = $product->is_ele;
                $tmp['control_price'] = $product->control_price;
                $tmp['stock'] = $product->stock;
                $tmp['sale_type'] = $product->sale_type;
                $tmp['sale_count'] = $product->sale_count;
                $tmp['status'] = $product->status;
                $tmp['number'] = $product->number;
                $tmp['product_date'] = $product->product_date;
                $tmp['product_end_date'] = $product->product_end_date;
                $tmp['detail'] = $product->detail;
                $tmp['weight'] = $product->weight;
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
        $name = $request->get("name", "");

        $query = SupplierDepot::select("id","cover","name","spec","unit","upc","status","manufacturer","approval","term_of_validity");

        if ($name) {
            $query->where("name", "like", "%{$name}%");
        }
        if ($upc) {
            $query->where("upc", $upc);
        }

        $depots = $query->orderBy("id", "desc")->paginate($page_size);

        if (!empty($depots)) {
            foreach ($depots as $depot) {
                $depot->yun = 0;
            }
        }


        return $this->page($depots);
    }

    public function depot_yun(Request $request)
    {
        $page_size = $request->get("page_size", 20);
        $upc = $request->get("upc", "");
        $name = $request->get("name", "");

        if (!$upc && !$name) {
            return $this->error('搜索云品库需添加搜索条件');
        }

        $query = MedicineDepot::select("id","cover","name","spec","upc")->where('cover', '<>', '');

        if ($name) {
            $query->where("name", "like", "%{$name}%");
        }
        if ($upc) {
            $query->where("upc", $upc);
        }

        $depots = $query->orderBy("id", "desc")->paginate($page_size);

        if (!empty($depots)) {
            foreach ($depots as $depot) {
                $depot->status = 1;
                $depot->yun = 1;
            }
        }


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
            "third_id" => $product->third_id,
            "stock" => $product->stock,
            "price" => (float) $product->price,
            "detail" => $product->detail,
            "sale_type" => $product->sale_type,
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

        $depot_data = $request->only("name","spec","unit","is_otc","upc","approval","cover","first_category","second_category","price","term_of_validity","manufacturer","generi_name");

        $depot_data['images'] = implode(",", $request->get("images"));

        // $product_data['user_id'] = $user->id;
        // $product_data['price'] = $request->get("price");
        // $product_data['is_control'] = $request->get("is_control", 0);
        // $product_data['control_price'] = $request->get("control_price", 0);
        // $product_data['stock'] = $request->get("stock");
        // $product_data['number'] = $request->get("number") ?? "";
        // $product_data['detail'] = $request->get("detail") ?? "";

        try {
            SupplierDepot::query()->create($depot_data);
            // DB::transaction(function () use ($depot_data, $product_data) {
            //     $depot = SupplierDepot::query()->create($depot_data);
            //
            //     $product_data['depot_id'] = $depot->id;
            //
            //     SupplierProduct::query()->create($product_data);
            // });
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
        $data = $request->only("stock","price", "status","number","product_date","product_end_date","weight","detail","third_id");
        $data['is_control'] = $request->get("is_control", 0);
        $data['is_meituan'] = $request->get("is_meituan", 0);
        $data['is_ele'] = $request->get("is_ele", 0);
        $data['control_price'] = $request->get("control_price", 0);

        if (!$product = SupplierProduct::find($request->get("id"))) {
            return $this->error("药品不存在");
        }

        if ($depot = SupplierDepot::find($product->depot_id)) {
            if (!empty($depot->term_of_validity) && intval($depot->term_of_validity) > 0) {
                $month = intval($depot->term_of_validity);
                $end_date = date("Y-m-d", strtotime("+{$month} month",strtotime($request->get("product_date"))) - 86400);
                \Log::info($end_date);
                $data['product_end_date'] = $end_date;
            }
        }

        $product->update($data);

        return $this->success();
    }

    /**
     * 删除商品
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
     * 上下架修改
     * @param Request $request
     * @return mixed
     */
    public function saleType(Request $request)
    {
        $user = Auth::user();
        $id = $request->get("id", 0);

        if (!$product = SupplierProduct::query()->where(['user_id' => $user->id, 'id' => $id])->first()) {
            return $this->error("数据不存在");
        }

        $product->sale_type = $product->sale_type === 2 ? 1 : 2;
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

        if ($depot->status !== 20) {
            return $this->error("商品正在审核中，请稍后");
        }

        $user = Auth::user();

        if ($product = SupplierProduct::query()->where(['depot_id' => $depot->id, "user_id" => $user->id])->first()) {
            return $this->error("商品已存在，不能重复添加");
        }

        $request->validate([
            // 'price' => 'bail|required|numeric|min:0',
            'stock' => 'bail|required|numeric',
            'number' => 'bail|required',
            'product_date' => 'bail|required|date',
            // 'product_end_date' => 'bail|required|date',
        ],[
            'price.required' => '药品价格不能为空',
            'price.numeric' => '药品价格格式不正确',
            // 'price.min' => '药品价格不能小于等于0',
            'stock.required' => '药品库存不能为空',
            'stock.numeric' => '药品库存格式不正确',
            'spec.required' => '药品规格不能为空',
            'number.required' => '批号不能为空',
            // 'product_date.required' => '生产日期不能为空',
            // 'product_end_date.required' => '有效日期不能为空',
        ]);

        $product_data['user_id'] = $user->id;
        $product_data['depot_id'] = $depot->id;
        $product_data['status'] = 20;
        $product_data['third_id'] = $request->get("third_id", "");
        $product_data['price'] = $request->get("price");
        $product_data['stock'] = $request->get("stock");
        $product_data['number'] = $request->get("number");
        $product_data['weight'] = $request->get("weight", 0);
        $product_data['detail'] = $request->get("detail", "");
        $product_data['product_date'] = $request->get("product_date");
        // $product_data['product_end_date'] = $request->get("product_date");
        $product_data['is_control'] = $request->get("is_control", 0);
        $product_data['is_meituan'] = $request->get("is_meituan", 0);
        $product_data['is_ele'] = $request->get("is_ele", 0);
        $product_data['control_price'] = $request->get("control_price", 0);

        if (!empty($depot->term_of_validity) && intval($depot->term_of_validity) > 0) {
            $month = intval($depot->term_of_validity);
            $end_date = date("Y-m-d", strtotime("+{$month} month",strtotime($request->get("product_date"))) - 86400);
            \Log::info($end_date);
            $product_data['product_end_date'] = $end_date;
        }
        SupplierProduct::query()->create($product_data);

        return $this->success();
    }

    /**
     * 品库中添加商品
     * @param Request $request
     * @return mixed
     */
    public function add_yun(Request $request)
    {
        if (!$yun = MedicineDepot::find($request->get("depot_id", 0))) {
            return $this->error("云品库中无此商品");
        }

        if (!$depot = SupplierDepot::where(['upc' => $yun->upc])->first()) {
            // $depot_data = $request->only("name","spec","unit","is_otc","upc","approval","cover","first_category","second_category","price","term_of_validity","manufacturer","generi_name");
            // $depot_data['images'] = implode(",", $request->get("images"));
            $depot = SupplierDepot::create([
                'name' => $yun->name,
                'spec' => $yun->spec,
                'upc' => $yun->upc,
                'cover' => $yun->cover,
                'first_category' => 1700,
                'second_category' => 1800167,
                'status' => 20,
                'images' => $yun->picture,
            ]);
        }

        if ($depot->status !== 20) {
            return $this->error("商品正在审核中，请稍后");
        }

        $user = $request->user();
        if ($product = SupplierProduct::where(['depot_id' => $depot->id, "user_id" => $user->id])->first()) {
            return $this->error("商品已存在，不能重复添加");
        }

        if ($depot->status !== 20) {
            return $this->error("商品正在审核中，请稍后");
        }

        $user = Auth::user();

        if ($product = SupplierProduct::query()->where(['depot_id' => $depot->id, "user_id" => $user->id])->first()) {
            return $this->error("商品已存在，不能重复添加");
        }

        $request->validate([
            // 'price' => 'bail|required|numeric|min:0',
            'stock' => 'bail|required|numeric',
            'number' => 'bail|required',
            'product_date' => 'bail|required|date',
            // 'product_end_date' => 'bail|required|date',
        ],[
            'price.required' => '药品价格不能为空',
            'price.numeric' => '药品价格格式不正确',
            // 'price.min' => '药品价格不能小于等于0',
            'stock.required' => '药品库存不能为空',
            'stock.numeric' => '药品库存格式不正确',
            'spec.required' => '药品规格不能为空',
            'number.required' => '批号不能为空',
            // 'product_date.required' => '生产日期不能为空',
            // 'product_end_date.required' => '有效日期不能为空',
        ]);

        $product_data['user_id'] = $user->id;
        $product_data['depot_id'] = $depot->id;
        $product_data['status'] = 20;
        $product_data['third_id'] = $request->get("third_id", "");
        $product_data['price'] = $request->get("price");
        $product_data['stock'] = $request->get("stock");
        $product_data['number'] = $request->get("number");
        $product_data['weight'] = $request->get("weight", 0);
        $product_data['detail'] = $request->get("detail", "");
        $product_data['product_date'] = $request->get("product_date");
        // $product_data['product_end_date'] = $request->get("product_date");
        $product_data['is_control'] = $request->get("is_control", 0);
        $product_data['is_meituan'] = $request->get("is_meituan", 0);
        $product_data['is_ele'] = $request->get("is_ele", 0);
        $product_data['control_price'] = $request->get("control_price", 0);

        if (!empty($depot->term_of_validity) && intval($depot->term_of_validity) > 0) {
            $month = intval($depot->term_of_validity);
            $end_date = date("Y-m-d", strtotime("+{$month} month",strtotime($request->get("product_date"))) - 86400);
            \Log::info($end_date);
            $product_data['product_end_date'] = $end_date;
        }
        SupplierProduct::query()->create($product_data);

        return $this->success();
    }

    public function category()
    {
        $result = [];

        // $categories = SupplierCategory::query()->select("id","parent_id","title")->where("status", 1)
        //     ->orderBy("parent_id")->get()->toArray();
        //
        // if (!empty($categories)) {
        //     foreach ($categories as $category) {
        //         if ($category['parent_id'] === 0) {
        //             $category['children'] = [];
        //             $result[$category['id']] = $category;
        //         } else {
        //             if (isset($result[$category['parent_id']])) {
        //                 $result[$category['parent_id']]['children'][] = $category;
        //             }
        //         }
        //     }
        // }

        $categories = Category::query()->select("id","pid as parent_id","title")
            ->orderBy("parent_id")->orderBy("sort")->get()->toArray();

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

    /**
     * 设置商品城市销售价格
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function setCityPrice(Request $request)
    {
        $user = Auth::user();
        if (!$product_id = $request->get('id', 0)) {
            return $this->error("商品不存在，请重新操作.");
        }

        $price = $request->get('price', null);

        if (is_null($price) || ($price < 0)) {
            return $this->error("价格有误，请重新操作.");
        }

        if (!$product = SupplierProduct::query()->where(['id' => $product_id, 'user_id' => $user->id])->first()) {
            return $this->error("商品不存在，请重新操作。");
        }

        $cities = $request->get("cities");

        if (!empty($cities)) {
            DB::transaction(function () use ($product_id, $price, $cities) {
                $insert_price = [
                    'product_id' => $product_id,
                    'price' => $price,
                ];
                if ($city_price = SupplierProductCityPrice::query()->create($insert_price)) {
                    SupplierProductCityPriceItem::query()->where('product_id', $product_id)->whereIn("city_code", $cities)->delete();
                    $data = [];
                    foreach ($cities as $city) {
                        $tmp['price_id'] = $city_price->id;
                        $tmp['product_id'] = $product_id;
                        $tmp['city_code'] = $city;
                        $tmp['price'] = $price;
                        $data[] = $tmp;
                    }
                    SupplierProductCityPriceItem::query()->insert($data);
                }
            });
        }

        return $this->success();
    }

    /**
     * 获取商品城市销售价格
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function getCityPrice(Request $request)
    {
        $user = Auth::user();

        $result = [];

        if (!$product_id = $request->get('id', 0)) {
            return $this->error("商品不存在，请重新操作.");
        }

        if (!$product = SupplierProduct::query()->where(['id' => $product_id, 'user_id' => $user->id])->first()) {
            return $this->error("商品不存在，请重新操作。");
        }

        $data = SupplierProductCityPrice::with('items.city')->where('product_id', $product_id)->get();


        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $title = [];
                if (!empty($v->items)) {
                    foreach ($v->items as $item) {
                        if (isset($item->city->title)) {
                            $title[] = $item->city->title;
                        }
                    }
                }
                if (!empty($title)) {
                    $tmp['id'] = $v['id'];
                    $tmp['price'] = $v['price'];
                    $tmp['city'] = implode(",", $title);
                    $result[] = $tmp;
                    unset($tmp);
                }
            }
        }

        return $this->success($result);
    }

    public function deleteCityPrice(Request $request)
    {
        if (!$id = $request->get("id")) {
            return $this->error("参数错误.");
        }

        $user = Auth::user();

        if (!$city_price = SupplierProductCityPrice::query()->find($id)) {
            return $this->error("参数错误!");
        }

        if (!$product = SupplierProduct::query()->find($city_price->product_id)) {
            return $this->error("参数错误!");
        }

        if ($user->id != $product->user_id) {
            return $this->error("参数错误!");
        }

        SupplierProductCityPriceItem::query()->where('price_id', $city_price->id)->delete();

        $city_price->delete();

        return $this->success();
    }
}
