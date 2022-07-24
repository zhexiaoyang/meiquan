<?php

namespace App\Jobs;

use App\Models\WmProduct;
use App\Models\WmProductSku;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MeiTuanTakeoutProductSave implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 类型 1、同步操作创建商品，2、迁移创建商品
    protected $type;
    protected $shop;
    protected $product;
    protected $skus;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $product, $shop, $skus = [])
    {
        $this->type = $type;
        $this->product = $product;
        $this->shop = $shop;
        $this->skus = $skus;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->type === 1) {
            return $this->meituan_save_product();
        } else if ($this->type === 2) {
            return $this->meiquan_save_product();
        }
    }

    public function meiquan_save_product()
    {
        $product = $this->product;
        $skus = $this->skus;

        $product_res = WmProduct::create($product);
        $sku_insert_arr = [];
        foreach ($skus as $sku) {
            $sku['product_id'] = $product_res->id;
            $sku_insert_arr[] = $sku;
        }
        if (!empty($sku_insert_arr)) {
            WmProductSku::insert($sku_insert_arr);
        }
    }

    public function meituan_save_product()
    {
        $shop = $this->shop;
        $product = $this->product;
        // 判断商家商品ID
        $app_food_code = $product['app_food_code'];
        if (empty($product)) {
            \Log::info("美团外卖创建商品，商品为空");
            return;
        }
        // 判断商家商品ID
        $app_food_code = $product['app_food_code'];
        // sku信息
        $save_skus = [];
        // 判断SKU
        $skus = json_decode(urldecode($product['skus']), true);
        if (!empty($skus)) {
            foreach ($skus as $k => $v) {
                // 附加信息
                $skus[$k]['shop_id'] = $shop->id;
                $skus[$k]['app_poi_code'] = $shop->waimai_mt;
                // 修改信息
                $skus[$k]['stock'] = $v['stock'] ?: 0;
                if (isset($skus[$k]['weight_for_unit'])) {
                    $skus[$k]['weight_for_unit'] = (float) $v['weight_for_unit'];
                }
                unset($skus[$k]['weight']);
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
            'app_poi_code' => $shop->waimai_mt,
            'app_food_code' => $app_food_code,
            'name' => $product['name'],
            'description' => $product['description'] ?? '',
            'standard_upc' => $product['standard_upc'] ?? '',
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
            'stock' => 0
        ];
        if ($wm_product = WmProduct::where(['app_food_code' => $app_food_code, 'shop_id' => $shop->id])->first()) {
            WmProduct::where('id', $wm_product->id)->update($params);
        } else {
            // 保存商品信息
            if ($res_product = WmProduct::create($params)) {
                $mt = '';
                if (!$app_food_code) {
                    $access_token = '';
                    if ($shop->meituan_bind_platform == 31) {
                        $mt = app("meiquan");
                        $access_token = $mt->getShopToken($shop->waimai_mt);
                    } else {
                        $mt = app("minkang");
                    }
                    $product_update_params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'name' => $res_product->name,
                        'category_code' => $res_product->category_code,
                    ];
                    if ($access_token) {
                        $product_update_params['access_token'] = $access_token;
                    }
                    $mt->updateAppFoodCodeByNameAndSpec($product_update_params);
                }
                foreach ($skus as $k => $sku) {
                    if (!$mt) {
                        $access_token = '';
                        if ($shop->meituan_bind_platform == 31) {
                            $mt = app("meiquan");
                            $access_token = $mt->getShopToken($shop->waimai_mt);
                        } else {
                            $mt = app("minkang");
                        }
                    }
                    $sku['product_id'] = $res_product->id;
                    $sku['available_times'] = json_encode($sku['available_times'], JSON_UNESCAPED_UNICODE);
                    $sku['app_food_code'] = $res_product->app_food_code;
                    if (empty($sku['sku_id'])) {
                        $_sku_id = $res_product->app_food_code;
                        if ($k) {
                            $_sku_id = $_sku_id . '_' . $k;
                        }
                        $sku['sku_id'] = $_sku_id;
                        $product_sku_update_params = [
                            'app_poi_code' => $shop->waimai_mt,
                            'name' => $res_product->name,
                            'category_code' => $res_product->category_code,
                            'sku_id' => $_sku_id,
                        ];
                        if ($sku['spec']) {
                            $product_sku_update_params['spec'] = $sku['spec'];
                        }
                        if (!$app_food_code) {
                            $app_food_code = $res_product->app_food_code;
                            $product_sku_update_params['app_spu_code'] = $res_product->app_food_code;
                        }
                        if ($access_token) {
                            $product_sku_update_params['access_token'] = $access_token;
                        }
                        $mt->updateAppFoodCodeByNameAndSpec($product_sku_update_params);
                    }
                    $save_skus[] = $sku;
                }
            }
            if (!empty($save_skus)) {
                WmProductSku::insert($save_skus);
            }
        }
    }
}
