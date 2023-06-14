<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Models\Medicine;
use App\Models\Shop;
use App\Models\VipProduct;
use App\Models\VipProductException;
use App\Models\WmProduct;
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
        $this->log_info('全部参数', $request->all());
        if (!empty($data)) {
            foreach ($data as $v) {
                $app_poi_code = $v['app_poi_code'];
                $upc = $v['upc'];
                if ($shop = Shop::select('id','shop_name')->where('waimai_mt', $app_poi_code)->where('vip_sync_status', 1)->first()) {
                    if ($product = VipProduct::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                        // $this->ding_exception("添加商品已存在,ID:{$product->id}|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        $this->log_info("添加商品已存在,ID:{$product->id}|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
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

                if ($app_poi_code == '13676234') {
                    $this->log_info('公园道店全部参数【创建】', $request->all());
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
        $this->log_info('全部参数', $request->all());

        if (!empty($data)) {
            foreach ($data as $v) {
                $app_poi_code = $v['app_poi_code'];
                $upc = $v['upc'] ?? '';
                if (!$upc) {
                    continue;
                }
                if ($shop = Shop::select('id')->where('waimai_mt', $app_poi_code)->first()) {
                    // 获取信息修改的信息
                    $price = $v['diff_contents']['skus'][0]['diffContentMap']['price']['result'] ?? null;
                    $is_sold_out = $v['diff_contents']['skus'][0]['diffContentMap']['is_sold_out']['result'] ?? null;
                    $stock = $v['diff_contents']['skus'][0]['diffContentMap']['stock']['result'] ?? null;
                    if (!is_null($price)) {
                        $this->log_info('修改价格');
                        if ($shop->vip_sync_status === 1) {
                            if ($product = VipProduct::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                                if (VipProduct::where('id', $product->id)->update(['price' => $price])) {
                                    $this->log_info("更新VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                                } else {
                                    // $this->ding_exception("更新VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                                }
                            } else {
                                // $this->ding_exception("更新商品,商品不存在|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                            }
                        }
                    }
                    if ($product = Medicine::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                        // 组合修改信息数组
                        $update_data = [];
                        if (!is_null($is_sold_out)) {
                            $update_data['online_mt'] = $is_sold_out ? 0 : 1;
                            $this->log_info("美团修改上下架|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc},is_sold_out:{$is_sold_out}");
                        }
                        if (!is_null($stock)) {
                            $update_data['stock'] = $stock;
                            $this->log_info("美团修改商品库存|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc},stock:{$stock}");
                        }
                        if (!is_null($price)) {
                            $update_data['price'] = $price;
                            $this->log_info("美团修改商品价格|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc},price:{$price}");
                        }
                        // 如果修改信息不为空，操作修改
                        if (!empty($update_data)) {
                            Medicine::where('id', $product->id)->update($update_data);
                            $this->log_info("美团修改商品信息操作成功");
                        }
                    }
                    // if (!is_null($is_sold_out)) {
                    //     $this->log_info('美团修改上下架');
                    //     if ($product = Medicine::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                    //         if (Medicine::where('id', $product->id)->update(['online_mt' => $is_sold_out ? 0 : 1])) {
                    //             $this->log_info("美团修改上下架成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc},is_sold_out:{$is_sold_out}");
                    //         }
                    //     }
                    // }
                    // if (!is_null($stock)) {
                    //     $this->log_info('美团修改商品库存');
                    //     if ($product = Medicine::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                    //         if (Medicine::where('id', $product->id)->update(['stock' => $stock])) {
                    //             $this->log_info("美团修改商品库存成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc},stock:{$stock}");
                    //         }
                    //     }
                    // }
                }

                // if ($app_poi_code == '13676234') {
                //     $this->log_info('公园道店全部参数【更新】', $request->all());
                // }
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
                $upc = $v['upc'] ?? '';
                if (!$upc) {
                    continue;
                }
                if ($shop = Shop::select('id')->where('waimai_mt', $app_poi_code)->where('vip_sync_status', 1)->first()) {
                    if ($product = VipProduct::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                        if ($product->delete()) {
                            $this->log_info("删除VIP商品成功|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        } else {
                            $this->log_info("删除VIP商品失败|门店:{$shop->id},门店:{$app_poi_code},upc:{$upc}");
                        }
                    }
                }

                if ($app_poi_code == '13676234') {
                    $this->log_info('公园道店全部参数【删除】', $request->all());
                }
            }
        }

        return json_encode(['data' => 'ok']);
    }
}
