<?php

namespace App\Http\Controllers;

use App\Exports\TakeoutProductExport;
use App\Exports\WmProductLogErrorExport;
use App\Imports\TakeoutProductImport;
use App\Jobs\EleUploadProduct;
use App\Jobs\MeiTuanTakeoutProductSave;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\WmCategory;
use App\Models\WmProduct;
use App\Models\WmProductLog;
use App\Models\WmProductLogItem;
use App\Models\WmProductSku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TakeoutProductController extends Controller
{
    public function update_cost(Request $request)
    {
        $sku_id = $request->get('id');
        $cost = (float) $request->get('cost');

        if ($cost < 0) {
            return $this->error('成本价格式错误');
        }

        if (!$sku = WmProductSku::find($sku_id)) {
            return $this->error('商品不存在');
        }

        if (!$sku->sku_id) {
            return $this->error('sku未绑定，不能进行此操作');
        }

        if (!$shop = Shop::find($sku->shop_id)) {
            return $this->error('门店不存在');
        }
        // 判断角色
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if ($shop->own_id != $request->user()->id) {
                return $this->error('无权限操作此商品');
            }
        }

        $sku->cost = $cost;
        $sku->save();

        return $this->success();
    }

    /**
     * 更改名称
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/20 9:11 下午
     */
    public function update_name(Request $request)
    {
        $product_id = $request->get('id');
        $type = $request->get('type');
        $value = $request->get('value');

        if (!$product_id || !$type || !$value) {
            return $this->error('参数错误');
        }

        if (!$product = WmProduct::find($product_id)) {
            return $this->error('商品不存在');
        }

        if (!$shop = Shop::find($product->shop_id)) {
            return $this->error('门店不存在');
        }

        $access_token = '';
        if ($shop->meituan_bind_platform == 31) {
            $mt = app("meiquan");
            $access_token = $mt->getShopToken($shop->waimai_mt);
        } else {
            $mt = app("minkang");
        }

        $shop_ids = OrderSetting::where('warehouse', $shop->id)->pluck('shop_id')->toArray();
        $shop_ids = WmProduct::whereIn('shop_id', $shop_ids)->groupBy('shop_id')->pluck('shop_id')->toArray();
        $shops = Shop::select('id', 'shop_name', 'waimai_mt', 'meituan_bind_platform')->whereIn('id', $shop_ids)->get();

        $stock_params = [
            'app_poi_code' => $shop->waimai_mt,
            'app_spu_code' => $product->app_food_code,
            'name' => $value
        ];
        if ($access_token) {
            $stock_params['access_token'] = $access_token;
        }
        $res = $mt->retailInitData($stock_params);
        $res_status = $res['data'] ?? '';
        if ($res_status == 'ok') {
            foreach ($shops as $shop_c) {
                if (!$shop_c->waimai_mt) {
                    continue;
                }
                $access_token = '';
                if ($shop_c->meituan_bind_platform == 31) {
                    $mt = app("meiquan");
                    $access_token = $mt->getShopToken($shop_c->waimai_mt);
                } else {
                    $mt = app("minkang");
                }
                $stock_params = [
                    'app_poi_code' => $shop_c->waimai_mt,
                    'app_spu_code' => $product->app_food_code,
                    'name' => $value
                ];
                if ($access_token) {
                    $stock_params['access_token'] = $access_token;
                }
                $ress = $mt->retailInitData($stock_params);
                \Log::info("ressss", [$ress]);
            }
            array_push($shop_ids, $shop->id);
            if ($type == 'name') {
                WmProduct::whereIn('shop_id', $shop_ids)->where('app_food_code', $product->app_food_code)->update([
                    'name' => $value
                ]);
            }
            return $this->success();
        }

        return $this->error($res['error']['msg'] ?? '更改失败', 422);
    }

    /**
     * 更新价格、库存
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/20 9:12 下午
     */
    public function update(Request $request)
    {
        $sku_id = $request->get('id');
        $type = $request->get('type');
        $value = $request->get('value');
        if (!$sku_id || !$type || is_null($value)) {
            return $this->error('参数错误');
        }

        if ($value < 0) {
            return $this->error('参数错误');
        }

        if (!$sku = WmProductSku::find($sku_id)) {
            return $this->error('商品不存在');
        }

        if (!$sku->sku_id) {
            return $this->error('sku未绑定，不能进行此操作');
        }

        if (!$shop = Shop::find($sku->shop_id)) {
            return $this->error('门店不存在');
        }

        $access_token = '';
        if ($shop->meituan_bind_platform == 31) {
            $mt = app("meiquan");
            $access_token = $mt->getShopToken($shop->waimai_mt);
        } else {
            $mt = app("minkang");
        }

        $sku_arr = [];
        $sku_arr['sku_id'] = $sku->sku_id;
        if ($type == 'stock') {
            $sku_arr['stock'] = $value;
        }
        if ($type == 'price') {
            $sku_arr['price'] = $value;
        }

        $food_data = [
            $sku_arr
        ];

        $shop_ids = OrderSetting::where('warehouse', $shop->id)->pluck('shop_id')->toArray();
        $shop_ids = WmProduct::whereIn('shop_id', $shop_ids)->groupBy('shop_id')->pluck('shop_id')->toArray();
        // array_push($shop_ids, $shop->id);
        $shops = Shop::select('id', 'shop_name', 'waimai_mt', 'meituan_bind_platform')->whereIn('id', $shop_ids)->get();

        $stock_params = [
            'app_poi_code' => $shop->waimai_mt,
            'app_spu_code' => $sku->app_food_code,
            'skus' => json_encode($food_data, JSON_UNESCAPED_UNICODE)
        ];
        if ($access_token) {
            $stock_params['access_token'] = $access_token;
        }
        $res = $mt->retailSkuSave($stock_params);
        $res_status = $res['data'] ?? '';
        if ($res_status == 'ok') {
            foreach ($shops as $shop_c) {
                if (!$shop_c->waimai_mt) {
                    continue;
                }
                $access_token = '';
                if ($shop_c->meituan_bind_platform == 31) {
                    $mt = app("meiquan");
                    $access_token = $mt->getShopToken($shop_c->waimai_mt);
                } else {
                    $mt = app("minkang");
                }
                $stock_params = [
                    'app_poi_code' => $shop_c->waimai_mt,
                    'app_spu_code' => $sku->app_food_code,
                    'skus' => json_encode($food_data, JSON_UNESCAPED_UNICODE)
                ];
                if ($access_token) {
                    $stock_params['access_token'] = $access_token;
                }
                $ress = $mt->retailSkuSave($stock_params);
                \Log::info("ressss", [$ress]);
            }
            array_push($shop_ids, $shop->id);
            if ($type == 'stock') {
                WmProductSku::whereIn('shop_id', $shop_ids)->where('sku_id', $sku->sku_id)->update([
                    'stock' => $value
                ]);
            } elseif ($type == 'price') {
                WmProductSku::whereIn('shop_id', $shop_ids)->where('sku_id', $sku->sku_id)->update([
                    'price' => $value
                ]);
            }
            return $this->success();
        }

        return $this->error($res['error']['msg'] ?? '更改失败', 422);
    }

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

        $query = WmProduct::with(['skus' => function ($query) {
            $query->select('id', 'product_id', 'price', 'cost', 'stock', 'spec');
        }])->select('id','name','price','cost_price','stock','picture')->where('shop_id', $shop_id);

        if ($category_id = $request->get('category')) {
            if ($category_id != 'all' && $category = WmCategory::find($category_id)) {
                $cat_arr = WmCategory::where('shop_id', $shop_id)->where('pid', $category->id)->pluck('code')->toArray();
                array_push($cat_arr, $category->code);
                $query->whereIn('category_code', $cat_arr);
            }
        }

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
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
        $categories = WmCategory::withCount(['products' => function($query) use ($shop_id) {
            $query->where('shop_id', $shop_id);
        }])->where('shop_id', $shop_id)->orderBy('pid')->get();
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

    /**
     * 商品迁移
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2022/7/8 11:04 下午
     */
    public function transfer(Request $request)
    {
        \Log::info("迁移商品开始");
        // 判断门店是否存在
        if (!$shop_id = $request->get('shop_id')) {
            \Log::info("迁移商品开始-请选择提供商品信息的门店");
            return $this->error('请选择提供商品信息的门店');
        }
        if (!$master_shop = Shop::find($shop_id)) {
            \Log::info("迁移商品开始-提供商品信息的门店不存在");
            return $this->error('提供商品信息的门店不存在');
        }
        // 获取权限和用户
        $has_permission = $request->user()->hasPermissionTo('currency_shop_all');
        $user_id = $request->user()->id;
        // 判断是否可以操作此门店
        if (!$has_permission) {
            if ($master_shop->own_id != $request->user()->id) {
                \Log::info("迁移商品开始-提供商品信息的门店不存在");
                return $this->error('提供商品信息的门店不存在');
            }
        }
        // 判断门店是否有分类
        $categories = [];
        $cat_res = WmCategory::where('shop_id', $master_shop->id)->orderBy('pid')->get();
        if ($cat_res->isEmpty()) {
            \Log::info("迁移商品开始-提供商品信息的门店没有商品");
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
        $products = WmProduct::with(['skus'])->where('shop_id', $master_shop->id)->get();
        if ($products->isEmpty()) {
            \Log::info("迁移商品开始-提供商品信息的门店没有商品");
            return $this->error('提供商品信息的门店没有商品');
        }
        \Log::info("迁移商品开始-迁移商品数量：" . count($products));
        // return count($products);

        // 判断补充商品门店
        $select_shops = $request->get('select_ids');
        if (empty($select_shops)) {
            \Log::info("迁移商品开始-请选择要补充商品的门店");
            return $this->error('请选择要补充商品的门店');
        }

        $shops = [];
        foreach ($select_shops as $item) {
            if ($shop = Shop::find($item)) {
                if ($has_permission || ($shop->own_id == $user_id)) {
                    if (!$shop->waimai_mt) {
                        \Log::info("迁移商品开始-{$shop->shop_name}未绑定开发者");
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
                    'fail' => 0,
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
                    \Log::info("迁移商品开始-同步分类结果", [$res]);
                    if (!empty($category['children'])) {
                        $insert_category = [];
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
                            \Log::info("迁移商品开始-同步二级分类结果", [$res2]);
                            $insert_category[] = [
                                'pid' => $cat->id,
                                'shop_id' => $shop->id,
                                'code' => $child['code'] ?? '',
                                'name' => $child['name'] ?? '',
                                'sequence' => $child['sequence'] ?? 0,
                                'top_flag' => $child['top_flag'] ?? 0,
                                'weeks_time' => $child['weeks_time'] ?? '',
                                'period' => $child['period'] ?? '',
                                'smart_switch' => $child['smart_switch'] ?? 0,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            ];
                        }
                        if (!empty($insert_category)) {
                            WmCategory::insert($insert_category);
                        }
                    }
                }
                $product_data = $products->chunk(200);
                \Log::info("迁移商品开始批数：" . count($product_data));
                foreach ($product_data as $key => $products) {
                    $pi = $key + 1;
                    // \Log::info("组合数据");
                    \Log::info("迁移商品开始-第{$pi}批迁移商品数量：" . count($products));
                    $batch_data = [];
                    $insert_data = [];
                    $insert_sku_data = [];
                    foreach ($products as $product) {
                        $_skus = [];
                        if (!empty($product->skus)) {
                            foreach ($product->skus as $v) {
                                $_sku = [
                                    'box_num' => $v->box_num,
                                    'box_price' => $v->box_price,
                                    'ladder_box_num' => $v->ladder_box_num,
                                    'ladder_box_price' => $v->ladder_box_price,
                                    'location_code' => $v->location_code,
                                    'min_order_count' => $v->min_order_count,
                                    'price' => $v->price,
                                    'sku_id' => $v->sku_id,
                                    'spec' => $v->spec,
                                    'unit' => $v->unit,
                                    'upc' => $v->upc,
                                    'weight_unit' => $v->weight_unit,
                                    'isSellFlag' => $v->isSellFlag,
                                    'weight_for_unit' => $v->weight_for_unit,
                                    'stock' => $v->stock,
                                    'limit_open_sync_stock_now' => $v->limit_open_sync_stock_now,
                                    'available_times' => json_decode($v->available_times, true),
                                ];
                                if (!$stock_type) {
                                    $_sku['stock'] = 0;
                                } else {
                                    if (!$online_type) {
                                        $_sku['is_sold_out'] = 1;
                                    }
                                }
                                $_skus[] = $_sku;
                                $add_sku = $v->toArray();
                                unset($add_sku['id']);
                                $add_sku['shop_id'] = $shop->id;
                                $add_sku['app_poi_code'] = $shop->waimai_mt;
                                $insert_sku_data[$product->app_food_code][] = $add_sku;
                            }
                        }
                        $params = [
                            // 'app_poi_code' => $shop->waimai_mt,
                            'app_spu_code' => $product->app_food_code,
                            'name' => $product->name,
                            'description' => $product->description,
                            'standard_upc' => $product->standard_upc,
                            'skus' => json_encode($_skus, JSON_UNESCAPED_UNICODE),
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
                        $batch_data[] = $params;
                        // 店铺新增数据
                        $add_product = $product->toArray();
                        $add_product['shop_id'] = $shop->id;
                        $add_product['app_poi_code'] = $shop->waimai_mt;
                        unset($add_product['id']);
                        unset($add_product['skus']);
                        unset($add_product['created_at']);
                        unset($add_product['updated_at']);
                        if (!$stock_type) {
                            $add_product['stock'] = 0;
                        } else {
                            if (!$online_type) {
                                $add_product['is_sold_out'] = 1;
                            }
                        }
                        $insert_data[$add_product['app_food_code']] = $add_product;
                        // if ($access_token) {
                        //     $params['access_token'] = $access_token;
                        // }
                        // $res = $mt->retailInitData($params);
                        // // \Log::info("商品", [$res]);
                        // if ($res['result_code'] == 1) {
                        //     $logs->success += 1;
                        //     $add_product = $product->toArray();
                        //     $add_product['shop_id'] = $shop->id;
                        //     unset($add_product['id']);
                        //     unset($add_product['created_at']);
                        //     unset($add_product['updated_at']);
                        //     if (!$stock_type) {
                        //         $add_product['stock'] = 0;
                        //     } else {
                        //         if (!$online_type) {
                        //             $add_product['is_sold_out'] = 1;
                        //         }
                        //     }
                        //     if (WmProduct::where(['app_food_code' => $product->app_food_code, 'shop_id' => $shop->id])->first()) {
                        //         WmProduct::where(['app_food_code' => $product->app_food_code, 'shop_id' => $shop->id])->update($add_product);
                        //     } else {
                        //         WmProduct::create($add_product);
                        //     }
                        // } elseif ($res['result_code'] == 2){
                        //     $logs->error += 1;
                        //     WmProductLogItem::insert([
                        //         'log_id' => $logs->id,
                        //         'name' => $product->name,
                        //         'type' => 1,
                        //         'description' => $res['error_list'][0]['msg'] ?? ''
                        //     ]);
                        // } else {
                        //     $logs->fail += 1;
                        //     WmProductLogItem::insert([
                        //         'log_id' => $logs->id,
                        //         'name' => $product->name,
                        //         'type' => 5,
                        //         'description' => $res['error_list'][0]['msg'] ?? ''
                        //     ]);
                        // }
                    }
                    $query_data = [
                        'app_poi_code' => $shop->waimai_mt,
                        'food_data' => json_encode($batch_data, JSON_UNESCAPED_UNICODE),
                    ];
                    if ($access_token) {
                        $query_data['access_token'] = $access_token;
                    }
                    // \Log::info("商品", [$insert_data]);
                    // \Log::info("商品", [$batch_data]);
                    // \Log::info("迁移商品开始-同步第{$key}批商品", [$batch_data]);
                    \Log::info("迁移商品开始-同步第{$pi}批商品");
                    \Log::info("迁移商品开始-请求美团创建商品");
                    $res = $mt->retailBatchInitData($query_data);
                    \Log::info("迁移商品开始-同步第{$pi}批商品返回结果", [$res]);
                    // \Log::info("商品", [$res]);
                    $error_list = $res['error_list'] ?? [];
                    $logs->success += count($products);
                    if (!empty($error_list)) {
                        \Log::info("迁移商品开始-同步第{$pi}批商品,有失败商品");
                        $error_data = [];
                        foreach ($error_list as $item) {
                            if ($item['blockFlag'] == 2) {
                                $logs->fail += 1;
                                $logs->success -= 1;
                                $error_data[] = [
                                    'log_id' => $logs->id,
                                    'name' => $insert_data[$item['app_spu_code']]['name'],
                                    'type' => 1,
                                    'description' => $res['error_list'][0]['msg'] ?? ''
                                ];
                                unset($insert_data[$item['app_spu_code']]);
                                unset($insert_sku_data[$item['app_spu_code']]);
                            }
                            if ($item['blockFlag'] == 1) {
                                $logs->success -= 1;
                                $logs->error += 1;
                                $error_data[] = [
                                    'log_id' => $logs->id,
                                    'name' => $insert_data[$item['app_spu_code']]['name'],
                                    'type' => 5,
                                    'description' => $res['error_list'][0]['msg'] ?? ''
                                ];
                                unset($insert_data[$item['app_spu_code']]);
                                unset($insert_sku_data[$item['app_spu_code']]);
                            }
                        }
                        if (!empty($error_data)) {
                            WmProductLogItem::insert($error_data);
                        }
                    }
                    // \Log::info("insert_data", $insert_data);
                    // \Log::info("insert_sku_data", $insert_sku_data);
                    \Log::info("迁移商品开始-同步第{$pi}批商品,准备插入数据");
                    if (!empty($insert_data)) {
                        \Log::info("迁移商品开始-同步第{$pi}批商品,插入数据");
                        foreach ($insert_data as $m) {
                            if (!empty($insert_sku_data[$m['app_food_code']])) {
                                MeiTuanTakeoutProductSave::dispatch(2, $m, null, $insert_sku_data[$m['app_food_code']]);
                            }
                            // // \Log::info("mmmmmm", [$m]);
                            // $product_res = WmProduct::create($m);
                            // // \Log::info('商品创建成功', [$product_res->id]);
                            // if (!empty($insert_sku_data[$product_res->app_food_code])) {
                            //     $sku_insert_arr = [];
                            //     foreach ($insert_sku_data[$product_res->app_food_code] as $n) {
                            //         $n['product_id'] = $product_res->id;
                            //         $sku_insert_arr[] = $n;
                            //     }
                            //     // \Log::info("nnnnnnnn", $sku_insert_arr);
                            //     WmProductSku::insert($sku_insert_arr);
                            // }
                        }
                    } else {
                        \Log::info("迁移商品开始-同步第{$pi}批商品,商品数组为空");
                        // \Log::info('迁移商品开始-商品数组为空');
                    }
                    // WmProduct::insert($insert_data);
                    \Log::info("迁移商品开始-同步第{$pi}批商品,结束");
                }
                \Log::info("迁移商品开始-更改日志");
                $logs->status = 1;
                $logs->total = $logs->success + $logs->fail;
                $logs->save();
            }
        }
        \Log::info("迁移商品开始-结束");
        return $this->success();
    }

    /**
     * 获取商品到中台
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
        \Log::info("同步商品开始时间" . date("Y-m-d H:i:s"));
        // $limit = 200;
        // $product_params = ['app_poi_code' => $shop->waimai_mt, 'offset' => 0, 'limit' => $limit];
        // for ($i = 0; $i < 2; $i++) {
        //     $product_params['offset'] = $i * $limit;
        //     if ($access_token) {
        //         $product_params['access_token'] = $access_token;
        //     }
        //     \Log::info("请求参数",$product_params);
        //     $products = $mt->retailList($product_params);
        //     if (!empty($products['data'])) {
        //         if (is_array($products['data'])) {
        //             foreach ($products['data'] as $product) {
        //                 MeiTuanTakeoutProductSave::dispatch(1, $product, $shop);
        //             }
        //         } else {
        //             \Log::info("非数组不能循环", [$products]);
        //         }
        //     } else {
        //         \Log::info("跳出循环", [$products]);
        //         break;
        //     }
        // }
        $product_params = ['app_poi_code' => $shop->waimai_mt];
        if ($access_token) {
            $product_params['access_token'] = $access_token;
        }
        $product_res = $mt->retailList($product_params);
        $products = $product_res['data'];
        if (!is_array($products)) {
            return $this->error('美团返回失败，请联系技术人员');
        }
        if (empty($products)) {
            return $this->error('未获取到商品信息');
        }
        foreach ($products as $product) {
            // MeiTuanTakeoutProductSave::dispatch(1, $product, $shop)->onConnection('product');
            MeiTuanTakeoutProductSave::dispatch(1, $product, $shop);
        }
        \Log::info("同步商品结束时间" . date("Y-m-d H:i:s"));

        return $this->success();
    }


    public function export(Request $request, TakeoutProductExport $export)
    {
        return $export->withRequest($request);
    }

    public function import(Request $request, TakeoutProductImport $import)
    {
        Excel::import($import, $request->file('file'));
        return back()->with('success', '导入成功');
    }

    public function store_ele(Request $request)
    {
        \Log::info("同步饿了么商品开始");
        // 判断门店是否存在
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择同步商品的门店');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }
        if (!$shop->waimai_ele) {
            return $this->error('未绑定开发者');
        }
        // 获取权限和用户
        $has_permission = $request->user()->hasPermissionTo('currency_shop_all');
        $user_id = $request->user()->id;
        // 判断是否可以操作此门店
        if (!$has_permission) {
            if ($shop->own_id != $user_id) {
                return $this->error('门店不存在！');
            }
        }
        // 判断门店是否有分类
        $categories = [];
        $cat_res = WmCategory::where('shop_id', $shop->id)->orderBy('pid')->get();
        if ($cat_res->isEmpty()) {
            return $this->error('门店没有商品分类');
        }
        foreach ($cat_res->toArray() as $cat) {
            if ($cat['pid'] == 0) {
                $categories[$cat['id']] = $cat;
            } else {
                $categories[$cat['pid']]['children'][] = $cat;
            }
        }
        // 判断门店是否有商品
        $products = WmProduct::with(['skus'])->where('shop_id', $shop->id)->get();
        if ($products->isEmpty()) {
            return $this->error('门店没有商品');
        }
        // 同步类型
        $stock_type = $request->get('stock_type');
        $online_type = $request->get('online_type');

        // 饿了么接口
        $ele = app("ele");

        // 同步分类
        foreach ($categories as $category) {
            $category_params = [
                'shop_id' => $shop->waimai_ele,
                'parent_category_id' => 0,
                'name' => $category['name'],
                'rank' => 10000 - $category['sequence'],
            ];
            $res = $ele->add_category($category_params);
            // \Log::info("res", [$res]);
            $category_id = $res['body']['data']['category_id'] ?? '';
            if ($category_id) {
                WmCategory::where('id', $category['id'])->update(['ele_id' => $category_id]);
                if (!empty($category['children'])) {
                    foreach ($category['children'] as $child) {
                        $category_params2 = [
                            'shop_id' => $shop->waimai_ele,
                            'parent_category_id' => $category_id,
                            'name' => $child['name'],
                            'rank' => 10000 - $child['sequence'],
                        ];
                        $res2 = $ele->add_category($category_params2);
                        $category_id2 = $res2['body']['data']['category_id'] ?? '';
                        if ($category_id2) {
                            WmCategory::where('id', $child['id'])->update(['ele_id' => $category_id2]);
                        }
                    }
                }
            }
        }

        // 同步商品
        foreach ($products as $product) {
            dispatch(new EleUploadProduct($product, $shop->waimai_ele));
        }

    }
}
