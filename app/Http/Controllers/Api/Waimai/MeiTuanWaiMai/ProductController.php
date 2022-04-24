<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Models\Shop;
use App\Models\VipProduct;
use App\Models\VipProductException;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Illuminate\Http\Request;

class ProductController
{
    use LogTool, NoticeTool;

    public $prefix_title = '[美团外卖商品操作回调&###]';

    public function create(Request $request, $platform)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode(urldecode($medicine_data), true);
        $app_poi_code = $data[0]['app_poi_code'] ?? '';

        if (!$app_poi_code) {
            return json_encode(['data' => 'ok']);
        }
        // 日志格式
        $this->prefix = str_replace('###', "新增商品" . get_meituan_develop_platform($platform) . "&美团门店:{$app_poi_code}", $this->prefix_title);
        // $this->log_info('全部参数', $request->all());
        if (!empty($data)) {
            foreach ($data as $v) {
                $app_poi_code = $v['app_poi_code'];
                $upc = $v['upc'];
                if ($shop = Shop::select('id','shop_name')->where('waimai_mt', $app_poi_code)->where('vip_sync_status', 1)->first()) {
                    if ($product = VipProduct::query()->where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                        $this->ding_exception("添加商品已存在,ID:{$product->id}|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                    } else {
                        $tmp = [
                            'platform_id' => $app_poi_code,
                            'shop_id' => $shop->id,
                            'shop_name' => $shop->shop_name,
                            'app_medicine_code' => $v['app_medicine_code'],
                            'name' => $v['name'],
                            'upc' => $v['upc'],
                            'medicine_no' => $v['medicine_no'] ?? '',
                            'spec' => $v['spec'] ?? '',
                            'price' => $v['price'] ?? 0,
                            'sequence' => $v['sequence'] ?? 0,
                            'category_name' => $v['category_name'] ?? '',
                            'stock' => intval($v['stock'] ?? 0),
                            'ctime' => time(),
                            'utime' => time(),
                            'platform' => 1,
                        ];
                        $p = VipProduct::create($tmp);
                        $tem_error = [
                            'product_id' => $p->id,
                            'shop_id' => $p->shop_id,
                            'platform_id' => $app_poi_code,
                            'shop_name' => $shop->shop_name,
                            'app_medicine_code' => $p->app_medicine_code,
                            'name' => $p->name,
                            'spec' => $p->spec,
                            'upc' => $p->upc,
                            'price' => $p->price,
                            'cost' => 0,
                            'platform' => $p->platform,
                            'error_type' => 1,
                            'error' => '成本价为0',
                        ];
                        VipProductException::create($tem_error);
                        $this->log_info("添加商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        // $this->ding_exception("添加商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                    }
                }
            }
        }

        return json_encode(['data' => 'ok']);
    }

    public function update(Request $request, $platform)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode(urldecode($medicine_data), true);
        $app_poi_code = $data[0]['app_poi_code'] ?? '';

        if (!$app_poi_code) {
            return json_encode(['data' => 'ok']);
        }
        // 日志格式
        $this->prefix = str_replace('###', "更新商品" . get_meituan_develop_platform($platform) . "&美团门店:{$app_poi_code}", $this->prefix_title);
        // $this->log_info('全部参数', $request->all());

        if (!empty($data)) {
            foreach ($data as $v) {
                $app_poi_code = $v['app_poi_code'];
                $upc = $v['upc'];
                $price = $v['diff_contents']['skus'][0]['diffContentMap']['price']['result'] ?? '';
                if ($price) {
                    $this->log_info('价格全部参数', $request->all());
                    if ($shop = Shop::select('id')->where('waimai_mt', $app_poi_code)->where('vip_sync_status', 1)->first()) {
                        if ($product = VipProduct::query()->where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                            if (VipProduct::query()->where('id', $product->id)->update(['price' => $price])) {
                                $this->log_info("更新VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                                $this->ding_exception("更新VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                            } else {
                                // $this->ding_exception("更新VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                            }
                        } else {
                            // $this->ding_exception("更新商品,商品不存在|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        }
                    }
                }
            }
        }

        return json_encode(['data' => 'ok']);
    }

    public function delete(Request $request, $platform)
    {
        $medicine_data = $request->get('medicine_data');
        $data = json_decode(urldecode($medicine_data), true);
        $app_poi_code = $data[0]['app_poi_code'] ?? '';

        if (!$app_poi_code) {
            return json_encode(['data' => 'ok']);
        }
        // 日志格式
        $this->prefix = str_replace('###', "删除商品" . get_meituan_develop_platform($platform) . "&美团门店:{$app_poi_code}", $this->prefix_title);
        // $this->log_info('全部参数', $request->all());
        if (!empty($data)) {
            foreach ($data as $v) {
                $app_poi_code = $v['app_poi_code'];
                $upc = $v['upc'];
                if ($shop = Shop::select('id')->where('waimai_mt', $app_poi_code)->where('vip_sync_status', 1)->first()) {
                    if ($product = VipProduct::query()->where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                        if ($product->delete()) {
                            $this->ding_exception("删除VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        } else {
                            $this->ding_exception("删除VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        }
                    }
                }
            }
        }

        return json_encode(['data' => 'ok']);
    }
}
