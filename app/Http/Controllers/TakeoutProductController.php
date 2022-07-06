<?php

namespace App\Http\Controllers;

use App\Exports\WmProductLogErrorExport;
use App\Models\Shop;
use App\Models\WmCategory;
use App\Models\WmProduct;
use App\Models\WmProductLog;
use App\Models\WmProductLogItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TakeoutProductController extends Controller
{
    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/5 6:07 下午
     */
    public function index(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if ($shop->own_id != $request->user()->id) {
                return $this->error('门店不存在');
            }
        }
        $page_size = $request->get('page_size', 10);

        $query = WmProduct::select('id','name','price','cost_price','stock','picture')->where('shop_id', $shop_id);

        if ($category_id = $request->get('category')) {
            if ($category_id != 'all' && $category = WmCategory::find($category_id)) {
                $cat_arr = WmCategory::where('shop_id', $shop_id)->where('pid', $category->id)->pluck('code')->toArray();
                array_push($cat_arr, $category->code);
                $query->whereIn('category_code', $cat_arr);
            }
        }

        $products = $query->paginate($page_size);

        return $this->success($products);
    }

    /**
     * 迁移记录
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/6 8:18 上午
     */
    public function log_index(Request $request)
    {
        $logs = WmProductLog::where('user_id', $request->user()->id)->orderByDesc('id')->limit(20)->get();

        return $this->success($logs);
    }

    /**
     * 导出失败同步信息
     * @author zhangzhen
     * @data 2022/7/6 8:59 上午
     */
    public function export_logs(Request $request, WmProductLogErrorExport $ordersExport)
    {
        if (!$log_id = $request->get('log_id')) {
            return $this->error('参数错误');
        }
        return $ordersExport->withRequest($log_id);
    }

    /**
     * 商品分类
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/5 6:07 下午
     */
    public function category_index(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }

        $res = [];
        $total = 0;
        $categories = WmCategory::withCount('products')->where('shop_id', $shop_id)->orderBy('pid')->get();
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $total += $category->products_count ?? 0;
                if ($category->pid == 0) {
                    $res[$category->id] = [
                        'key' => $category->id,
                        'title' => $category->name,
                        'count' => $category->products_count ?? 0,
                    ];
                } else {
                    $res[$category->pid]['children'][] = [
                        'key' => $category->id,
                        'title' => $category->name,
                        'count' => $category->products_count ?? 0,
                    ];
                    $res[$category->pid]['count'] += $category->products_count ?? 0;
                }
            }
        }
        array_unshift($res, [
            'key' => 'all',
            'title' => '全部商品',
            'count' => $total
        ]);

        foreach ($res as $k => $v) {
            $res[$k]['title'] = $v['title'] . "({$v['count']})";
            if (!empty($v['children'])) {
                foreach ($v['children'] as $m => $n) {
                    $res[$k]['children'][$m]['title'] = $n['title'] . "({$n['count']})";
                }
            }
        }

        return $this->success(array_values($res));
    }

    public function transfer(Request $request)
    {
        // 判断门店是否存在
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择提供商品信息的门店');
        }
        if (!$master_shop = Shop::find($shop_id)) {
            return $this->error('提供商品信息的门店不存在');
        }
        // 获取权限和用户
        $has_permission = $request->user()->hasPermissionTo('currency_shop_all');
        $user_id = $request->user()->id;
        // 判断是否可以操作此门店
        if (!$has_permission) {
            if ($master_shop->own_id != $request->user()->id) {
                return $this->error('提供商品信息的门店不存在');
            }
        }
        // 判断门店是否有分类
        $categories = [];
        $cat_res = WmCategory::where('shop_id', $master_shop->id)->orderBy('pid')->get();
        if ($cat_res->isEmpty()) {
            return $this->error('提供商品信息的门店没有商品！');
        }
        foreach ($cat_res->toArray() as $cat) {
            if ($cat['pid'] == 0) {
                $categories[$cat['id']] = $cat;
            } else {
                $categories[$cat['pid']]['children'][] = $cat;
            }
        }
        // 判断门店是否有商品
        $products = WmProduct::where('shop_id', $master_shop->id)->get();
        if ($products->isEmpty()) {
            return $this->error('提供商品信息的门店没有商品');
        }
        // return count($products);

        // 判断补充商品门店
        $select_shops = $request->get('select_ids');
        if (empty($select_shops)) {
            return $this->error('请选择要补充商品的门店');
        }

        $shops = [];
        foreach ($select_shops as $item) {
            if ($shop = Shop::find($item)) {
                if ($has_permission || ($shop->own_id == $user_id)) {
                    if (!$shop->waimai_mt) {
                        return $this->error($shop->shop_name . ' 未绑定开发者');
                    }
                    $shops[] = $shop;
                }
            }
        }

        $stock_type = $request->get('stock_type');
        $online_type = $request->get('online_type');

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $logs = WmProductLog::create([
                    'from_shop' => $master_shop->id,
                    'from_shop_name' => $master_shop->shop_name,
                    'go_shop' => $shop->id,
                    'go_shop_name' => $shop->shop_name,
                    'user_id' => $user_id,
                    'success' => 0,
                    'error' => 0,
                ]);
                $access_token = '';
                if ($shop->meituan_bind_platform == 31) {
                    $mt = app("meiquan");
                    $access_token = $mt->getShopToken($shop->waimai_mt);
                } else {
                    $mt = app("minkang");
                }
                foreach ($categories as $category) {
                    $category_params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'category_code' => $category['code'],
                        'category_name' => $category['name'],
                        'sequence' => $category['sequence'],
                    ];
                    if ($access_token) {
                        $category_params['access_token'] = $access_token;
                    }
                    $res = $mt->retailCatUpdate($category_params);
                    $cat = WmCategory::create([
                        'shop_id' => $shop->id,
                        'code' => $category['code'] ?? '',
                        'name' => $category['name'] ?? '',
                        'sequence' => $category['sequence'] ?? 0,
                        'top_flag' => $category['top_flag'] ?? 0,
                        'weeks_time' => $category['weeks_time'] ?? '',
                        'period' => $category['period'] ?? '',
                        'smart_switch' => $category['smart_switch'] ?? 0,
                    ]);
                    // \Log::info("res", [$res]);
                    if (!empty($category['children'])) {
                        foreach ($category['children'] as $child) {
                            $category_params2 = [
                                'app_poi_code' => $shop->waimai_mt,
                                'category_name_origin' => $category['name'],
                                'category_name' => $category['name'],
                                'secondary_category_name' => $child['name'],
                                'secondary_category_code' => $child['code'],
                                'sequence' => $child['sequence'],
                            ];
                            if ($access_token) {
                                $category_params2['access_token'] = $access_token;
                            }
                            $res2 = $mt->retailCatUpdate($category_params2);
                            // \Log::info("res2", [$res2]);
                            WmCategory::create([
                                'pid' => $cat->id,
                                'shop_id' => $shop->id,
                                'code' => $category['code'] ?? '',
                                'name' => $category['name'] ?? '',
                                'sequence' => $category['sequence'] ?? 0,
                                'top_flag' => $category['top_flag'] ?? 0,
                                'weeks_time' => $category['weeks_time'] ?? '',
                                'period' => $category['period'] ?? '',
                                'smart_switch' => $category['smart_switch'] ?? 0,
                            ]);
                        }
                    }
                }
                foreach ($products as $product) {
                    $params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_food_code' => $product->app_food_code,
                        'name' => $product->name,
                        'description' => $product->description,
                        'standard_upc' => $product->standard_upc,
                        'skus' => $product->skus,
                        'price' => $product->price,
                        'min_order_count' => $product->min_order_count,
                        'unit' => $product->unit,
                        'box_num' => $product->box_num,
                        'box_price' => $product->box_price,
                        'category_code' => $product->category_code,
                        'is_sold_out' => $product->is_sold_out,
                        'picture' => $product->picture,
                        'sequence' => $product->sequence,
                        'tag_id' => $product->tag_id,
                        'picture_contents' => $product->picture_contents,
                        'is_specialty' => $product->is_specialty,
                        'video_id' => $product->video_id,
                        'common_attr_value' => $product->common_attr_value,
                        'is_show_upc_pic_contents' => $product->is_show_upc_pic_contents,
                        'limit_sale_info' => $product->limit_sale_info,
                        // 'sale_type' => $product->sale_type,
                    ];
                    if (!$stock_type) {
                        $skus = json_decode($product->skus, true);
                        $skus[0]['stock'] = 0;
                        $params['skus'] = json_encode($skus, JSON_UNESCAPED_UNICODE);
                    } else {
                        if (!$online_type) {
                            $params['is_sold_out'] = 1;
                        }
                    }
                    if ($access_token) {
                        $params['access_token'] = $access_token;
                    }
                    $res = $mt->retailInitData($params);
                    \Log::info("商品", [$res]);
                    if ($res['result_code'] == 1) {
                        $logs->success += 1;
                        $add_product = $product->toArray();
                        $add_product['shop_id'] = $shop->id;
                        unset($add_product['id']);
                        unset($add_product['created_at']);
                        unset($add_product['updated_at']);
                        if (!$stock_type) {
                            $add_product['stock'] = 0;
                        } else {
                            if (!$online_type) {
                                $add_product['is_sold_out'] = 1;
                            }
                        }
                        WmProduct::create($add_product);
                    } elseif ($res['result_code'] == 2){
                        $logs->error += 1;
                        WmProductLogItem::insert([
                            'log_id' => $logs->id,
                            'name' => $product->name,
                            'type' => 1,
                            'description' => $res['error_list'][0]['msg'] ?? ''
                        ]);
                    } else {
                        $logs->error += 1;
                        WmProductLogItem::insert([
                            'log_id' => $logs->id,
                            'name' => $product->name,
                            'type' => 5,
                            'description' => $res['error_list'][0]['msg'] ?? ''
                        ]);
                    }
                }
                $logs->status = 1;
                $logs->total = $logs->success + $logs->error;
                $logs->save();
            }
        }
        return $this->success();
    }

    /**
     * 获取商品
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/5 6:07 下午
     */
    public function store(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if ($shop->own_id != $request->user()->id) {
                return $this->error('门店不存在');
            }
        }

        if (!$shop->waimai_mt) {
            return $this->error('该店没有绑定美团开发者，请先绑定');
        }

        $access_token = '';
        if ($shop->meituan_bind_platform == 31) {
            $mt = app("meiquan");
            $access_token = $mt->getShopToken($shop->waimai_mt);
        } else {
            $mt = app("minkang");
        }
        $shop_params = ['app_poi_codes' => $shop->waimai_mt];
        if ($access_token) {
            $shop_params['access_token'] = $access_token;
        }
        $mq_shops = $mt->getShops($shop_params);
        if (empty($mq_shops['data'])) {
            return $this->error('该店没有绑定美团开发者，请先绑定');
        }

        // 同步分类
        $category_params = [ 'app_poi_code' => $shop->waimai_mt, ];
        if ($access_token) {
            $category_params['access_token'] = $access_token;
        }
        $categories = $mt->retailCatList($category_params);
        // return $categories;
        if (!empty($categories['data'])) {
            DB::transaction(function () use ($categories, $shop, $access_token, $mt) {
                foreach ($categories['data'] as $category) {
                    if ($category['code'] && $cat_model = WmCategory::where('shop_id', $shop->id)->where('code', $category['code'])->first()) {
                        $cat_model->update([
                            'name' => $category['name'] ?? '',
                            'sequence' => $category['sequence'] ?? 0,
                            'top_flag' => $category['top_flag'] ?? 0,
                            'weeks_time' => $category['weeks_time'] ?? '',
                            'period' => $category['period'] ?? '',
                            'smart_switch' => $category['smart_switch'] ?? 0,
                        ]);
                    } else {
                        $cat = WmCategory::create([
                            'shop_id' => $shop->id,
                            'code' => $category['code'] ?? '',
                            'name' => $category['name'] ?? '',
                            'sequence' => $category['sequence'] ?? 0,
                            'top_flag' => $category['top_flag'] ?? 0,
                            'weeks_time' => $category['weeks_time'] ?? '',
                            'period' => $category['period'] ?? '',
                            'smart_switch' => $category['smart_switch'] ?? 0,
                        ]);
                        if (!$category['code']) {
                            $category_update_params = [
                                'app_poi_code' => $shop->waimai_mt,
                                'category_name_origin' => $category['name'],
                                'category_name' => $category['name'],
                                'category_code' => $cat->code,
                            ];
                            if ($access_token) {
                                $category_update_params['access_token'] = $access_token;
                            }
                            $res = $mt->retailCatUpdate($category_update_params);
                            \Log::info("res", [$res]);
                        }
                    }
                    // 二级分类
                    if (!empty($category['children'])) {
                        foreach ($category['children'] as $category2) {
                            if ($category2['code'] && $cat_model = WmCategory::where('shop_id', $shop->id)->where('code', $category2['code'])->first()) {
                                $cat_model->update([
                                    'name' => $category2['name'] ?? '',
                                    'sequence' => $category2['sequence'] ?? 0,
                                    'top_flag' => $category2['top_flag'] ?? 0,
                                    'weeks_time' => $category2['weeks_time'] ?? '',
                                    'period' => $category2['period'] ?? '',
                                    'smart_switch' => $category2['smart_switch'] ?? 0,
                                ]);
                            } else {
                                $cat2 = WmCategory::create([
                                    'pid' => $cat->id,
                                    'shop_id' => $shop->id,
                                    'code' => $category2['code'] ?? '',
                                    'name' => $category2['name'] ?? '',
                                    'sequence' => $category2['sequence'] ?? 0,
                                    'top_flag' => $category2['top_flag'] ?? 0,
                                    'weeks_time' => $category2['weeks_time'] ?? '',
                                    'period' => $category2['period'] ?? '',
                                    'smart_switch' => $category2['smart_switch'] ?? 0,
                                ]);
                                if (!$category2['code']) {
                                    $category_update_params2 = [
                                        'app_poi_code' => $shop->waimai_mt,
                                        'category_name_origin' => $category2['name'],
                                        'category_name' => $category2['name'],
                                        'category_code' => $cat2->code,
                                        // 'secondary_category_name' => $category2['name'],
                                        // 'secondary_category_code' => $cat2->id
                                    ];
                                    if ($access_token) {
                                        $category_update_params2['access_token'] = $access_token;
                                    }
                                    $res = $mt->retailCatUpdate($category_update_params2);
                                    \Log::info("res", [$res]);
                                }
                            }
                        }
                    }
                }
            });
        } else {
            return $this->error('未获取到分类信息');
        }


        // 同步商品
        DB::transaction(function () use ($shop, $access_token, $mt) {
            $product_params = ['app_poi_code' => $shop->waimai_mt, 'offset' => 0, 'limit' => 200];
            // 36，37
            for ($i = 0; $i < 100; $i++) {
                $product_params['offset'] = $i * 200;
                if ($access_token) {
                    $product_params['access_token'] = $access_token;
                }
                $products = $mt->retailList($product_params);
                if (!empty($products['data'])) {
                    foreach ($products['data'] as $product) {
                        // 判断商家商品ID
                        $app_food_code = $product['app_food_code'];
                        // 判断SKU
                        $skus = json_decode(urldecode($product['skus']), true);
                        $stock = 0;
                        if (!empty($skus)) {
                            foreach ($skus as $k => $v) {
                                $skus[$k]['stock'] = $v['stock'] ?: 0;
                                $skus[$k]['weight_for_unit'] = (float) $v['weight_for_unit'];
                                $stock += $v['stock'] ?: 0;
                                unset($skus[$k]['weight']);
                                if (!$v['sku_id']) {
                                    $sku_id = $app_food_code;
                                    if ($k) {
                                        $sku_id = $sku_id . $k;
                                    }
                                    $skus[$k]['sku_id'] = $sku_id;
                                }
                            }
                        }
                        $common_attr_values = json_decode(urldecode($product['common_attr_value']), true);
                        if (!empty($common_attr_values)) {
                            foreach ($common_attr_values as $k => $common_attr_value) {
                                if (!empty($common_attr_value['valueList'])) {
                                    $common_attr_values[$k]['valueList'] = $common_attr_value['valueList'][0];
                                    unset($common_attr_values[$k]['valueListSize']);
                                    unset($common_attr_values[$k]['valueListIterator']);
                                    unset($common_attr_values[$k]['setValue']);
                                    unset($common_attr_values[$k]['setValueId']);
                                }
                            }
                        }
                        $params = [
                            'shop_id' => $shop->id,
                            'app_food_code' => $app_food_code,
                            'name' => $product['name'],
                            'description' => $product['description'] ?? '',
                            'standard_upc' => $product['standard_upc'] ?? '',
                            'skus' => json_encode($skus, JSON_UNESCAPED_UNICODE),
                            'price' => $product['price'] ?? 0,
                            'min_order_count' => $product['min_order_count'] ?? 1,
                            'unit' => $product['unit'] ?? '',
                            'box_num' => $product['box_num'] ?? 0,
                            'box_price' => $product['box_price'] ?? 0,
                            'category_code' => $product['secondary_category_code'] ?: $product['category_code'],
                            'category_name' => $product['secondary_category_name'] ?: $product['category_name'],
                            'is_sold_out' => $product['is_sold_out'] ?? 0,
                            'picture' => $product['picture'] ?? '',
                            'sequence' => $product['sequence'] ?? -1,
                            'tag_id' => $product['tag_id'] ?? 0,
                            'picture_contents' => $product['picture_contents'] ?? '',
                            'is_specialty' => $product['is_specialty'] ?? 0,
                            'video_id' => $product['video_id'] ?? 0,
                            'common_attr_value' => json_encode($common_attr_values, JSON_UNESCAPED_UNICODE),
                            'is_show_upc_pic_contents' => $product['is_show_upc_pic_contents'] ?? 1,
                            'limit_sale_info' => $product['limit_sale_info'] ?? '',
                            'sale_type' => $product['sale_type'] ?? 0,
                            'stock' => $stock
                        ];
                        $pro = WmProduct::create($params);
                        if (!$app_food_code) {
                            $product_update_params = [
                                'app_poi_code' => $shop->waimai_mt,
                                'name' => $pro->name,
                                'category_code' => $pro->category_code,
                                'app_spu_code' => $pro->app_food_code,
                                'sku_id' => $pro->app_food_code,
                            ];
                            if ($access_token) {
                                $product_update_params['access_token'] = $access_token;
                            }
                            $mt->updateAppFoodCodeByNameAndSpec($product_update_params);
                        }
                    }
                } else {
                    break;
                }
            }
        });

        return $this->success();
    }
}
