<?php

namespace App\Http\Controllers;

use App\Exports\WmRetailExport;
use App\Imports\RetailImport;
use App\Models\RetailSelectShop;
use App\Models\Shop;
use App\Models\WmRetail;
use App\Models\WmRetailSku;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RetailController extends Controller
{
    /**
     * 门店列表
     */
    public function shops(Request $request)
    {
        $query = Shop::select('id', 'shop_name', 'erp_status')->where('second_category', '<>', 200001)->where('user_id', '>', 0)->where('status', '>=', 0);
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id')->toArray());
        }
        if ($name = $request->get('name')) {
            $shops = $query->where('shop_name', 'like', "%{$name}%")->orderBy('id')->limit(30)->get();
        } else {
            if ($select_shops = RetailSelectShop::where('user_id', $request->user()->id)->first()) {
                $shops = $query->orderBy('id')->limit(14)->get();
                $shop_select = Shop::select('id', 'shop_name', 'erp_status')->find($select_shops->shop_id);
                $shops->prepend($shop_select);
            } else {
                $shops = $query->orderBy('id')->limit(15)->get();
            }
        }

        return $this->success($shops);
    }

    /**
     * 商品列表
     * @author zhangzhen
     * @data 2023/10/11 4:29 下午
     */
    public function product(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!Shop::find($shop_id)) {
            return $this->error('门店错误.');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店错误');
            }
        }

        $user_id = $request->user()->id;
        RetailSelectShop::updateOrCreate(
            [ 'user_id' => $user_id ],
            [ 'user_id' => $user_id, 'shop_id' => $shop_id ]
        );

        $query = WmRetailSku::where('shop_id', $shop_id);

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        // 毛利率筛选
        $gpm_max = $request->get('gpm_max');
        if (is_numeric($gpm_max)) {
            $query->where('gpm', '<=', $gpm_max);
        }
        $gpm_min = $request->get('gpm_min');
        if (is_numeric($gpm_min)) {
            $query->where('gpm', '>=', $gpm_min);
        }
        // 线下毛利率筛选
        $gpm_offline_max = $request->get('gpm_offline_max');
        if (is_numeric($gpm_offline_max)) {
            $query->where('down_gpm', '<=', $gpm_offline_max);
        }
        $gpm_offline_min = $request->get('gpm_offline_min');
        if (is_numeric($gpm_offline_min)) {
            $query->where('down_gpm', '>=', $gpm_offline_min);
        }
        if ($sku = $request->get('sku')) {
            $query->where('sku_id', $sku);
        }
        if ($excep = $request->get('exception')) {
            $excep = intval($excep);
            if ($excep === 2) {
                $query->whereColumn('guidance_price', '>', 'price');
            }
        }

        $data =$query->paginate($request->get('page_size', 10));

        return $this->page($data, [],'data');
    }

    /**
     * 更新成本价
     * @author zhangzhen
     * @data 2023/10/11 4:29 下午
     */
    public function update(Request $request)
    {
        $guidance_price = $request->get('guidance_price');
        if (!is_numeric($guidance_price)) {
            return $this->error('成本价不合法');
        }
        if ($guidance_price < 0) {
            return $this->error('成本价不能小于0');
        }
        if (!$sku_id = $request->get('id')) {
            return $this->error('商品不存在');
        }
        if (!$sku = WmRetailSku::find($sku_id)) {
            return $this->error('商品不存在');
        }
        $shop_id = $sku->shop_id;
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('商品不存在');
            }
        }
        $sku->guidance_price = $guidance_price;
        $sku->save();

        return $this->success();
    }

    /**
     * 统计
     * @author zhangzhen
     * @data 2023/10/11 4:30 下午
     */
    public function statistics_status(Request $request)
    {
        $data = [
            'total' => 0,
            'price_exception' => 0
        ];
        if ($shop_id = $request->get('shop_id')) {
            $medicines = WmRetailSku::where('shop_id', $shop_id)->get();
            if (!empty($medicines)) {
                foreach ($medicines as $medicine) {
                    $data['total']++;
                    if ($medicine->price < $medicine->guidance_price) {
                        $data['price_exception']++;
                    }
                }
            }
        }
        return $this->success($data);
    }

    /**
     * 同步美团商品到中台
     * @author zhangzhen
     * @data dateTime
     */
    public function fromMeituan(Request $request)
    {
        $shop_id = $request->get('shop_id', 0);
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop->id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店不存在。');
            }
        }
        if (!$shop->waimai_mt) {
            return $this->error('门店未绑定美团');
        }
        // if (WmRetailSku::where('shop_id', $shop_id)->count() > 0) {
        //     return $this->error('该门店已同步过商品，请先清空后再操作同步');
        // }
        $waimai_mt = $shop->waimai_mt;
        if ($shop->meituan_bind_platform === 25) {
            $mt = app('mtkf');
            $page_size = 200;
            for ($i = 0; $i < 50; $i ++) {
                $res = $mt->wmoper_food_list($shop_id, $i * $page_size, $page_size);
                $data = $res['data'] ?? [];
                if (!empty($data)) {
                    foreach ($data as $v) {
                        $skus_str = $v['skus'];
                        $skus = json_decode($skus_str, true);
                        if (empty($skus)) {
                            continue;
                        }
                        $app_food_code = $v['app_food_code'];
                        $name = $v['name'];
                        $category_name = $v['category_name'];
                        $picture = $v['picture'];
                        $sequence = $v['sequence'];
                        // $pictures = $v['pictures'];
                        $picture = str_replace('http:', 'https:', $picture);
                        // $pictures = str_replace('http:', 'https:', $pictures);
                        $retail = WmRetail::updateOrCreate([
                            'shop_id' => $shop_id,
                            'store_id' => $app_food_code ?: $name,
                            'name' => $name,
                            'category' => $category_name,
                            'cover' => $picture,
                            'sequence' => $sequence,
                        ], ['shop_id' => $shop_id, 'store_id' => $app_food_code ?: $name]);
                        foreach ($skus as $sku) {
                            $sku_id = $sku['sku_id'];
                            $price = $sku['price'];
                            $spec = $sku['spec'];
                            if (!$sku_id) {
                                $sku_id = $name . '-' . $spec;
                            }
                            WmRetailSku::updateOrCreate([
                                'retail_id' => $retail->id,
                                'shop_id' => $shop_id,
                                'sku_id' => $sku_id,
                                'name' => $name,
                                'category' => $category_name,
                                'cover' => $picture,
                                'sequence' => $sequence,
                                'price' => $price,
                                'spec' => $spec,
                                'mt_status' => 1,
                                'online_mt' => 1,
                            ], ['shop_id' => $shop_id, 'retail_id' => $retail->id, 'sku_id' => $sku_id]);
                            // Log::info("$name-$spec|$sku_id|$price|$category_name|$picture|$pictures");
                        }
                    }
                } else {
                    break;
                }
            }
        } else if ($shop->meituan_bind_platform === 31) {
            $mt = app('meiquan');
            for ($i = 0; $i < 50; $i++) {
                $products = $mt->retailList(['app_poi_code' => $waimai_mt, 'access_token' => $mt->getShopToken($waimai_mt),'offset' => $i, 'limit' => 200]);
                if (!empty($products['data']) && is_array($products['data'])) {
                    foreach ($products['data'] as $v) {
                        $app_food_code = $v['app_spu_code'];
                        $name = $v['name'];
                        $category_name = $v['category_name'];
                        $sequence = $v['sequence'];
                        $picture = $v['picture'];
                        $offset = stripos($picture, ',');
                        if ($offset !== false) {
                            $picture = substr($picture, 0, $offset );
                        }
                        $picture = str_replace('http:', 'https:', $picture);
                        $retail = WmRetail::create([
                            'shop_id' => $shop_id,
                            'store_id' => $app_food_code ?: $name,
                            'name' => $name,
                            'category' => $category_name,
                            'cover' => $picture,
                            'sequence' => $sequence,
                        ]);
                        // 判断SKU
                        $skus = json_decode(urldecode($v['skus']), true);
                        if (!empty($skus)) {
                            foreach ($skus as $sku) {
                                $sku_id = $sku['sku_id'];
                                $price = $sku['price'];
                                $spec = $sku['spec'];
                                if (!$sku_id) {
                                    $sku_id = $name . '-' . $spec;
                                }
                                WmRetailSku::create([
                                    'retail_id' => $retail->id,
                                    'shop_id' => $shop_id,
                                    'sku_id' => $sku_id,
                                    'name' => $name,
                                    'category' => $category_name,
                                    'cover' => $picture,
                                    'sequence' => $sequence,
                                    'price' => $price,
                                    'spec' => $spec,
                                    'mt_status' => 1,
                                    'online_mt' => 1,
                                ]);
                            }
                        }

                    }
                } else {
                    break;
                }
            }
        } else {
            return $this->error('餐饮不支持此操作');
        }
    }

    /**
     * 导出
     * @author zhangzhen
     * @data 2023/10/11 2:29 下午
     */
    public function export_retail(Request $request, WmRetailExport $export)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        return $export->withRequest($shop_id);
    }

    /**
     * 导入
     * @author zhangzhen
     * @data 2023/10/11 4:30 下午
     */
    public function import(Request $request, RetailImport $import)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        $import->shop_id = $shop_id;
        Excel::import($import, $request->file('file'));
        return $this->success();
    }

    /**
     * 清空门店中台商品
     * @author zhangzhen
     * @data 2023/10/11 4:30 下午
     */
    public function clear(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!Shop::find($shop_id)) {
            return $this->error('门店错误.');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id')->toArray())) {
                return $this->error('门店错误');
            }
        }
        WmRetail::where('shop_id', $shop_id)->delete();
        WmRetailSku::where('shop_id', $shop_id)->delete();
        \Log::info("操作清空商品", [$request->user(), $request->all()]);
        return $this->success();
    }
}
