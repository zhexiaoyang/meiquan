<?php

namespace App\Jobs;

use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Models\WmProduct;
use App\Traits\LogTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarehouseCancelOrderStockSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogTool;

    public $order;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(WmOrder $order)
    {
        $this->prefix = "仓库取消订单库存恢复|订单ID:{$order->id}|订单号:{$order->order_id}|门店ID:{$order->shop_id}";
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shop_id = $this->order->shop_id;
        $warehouse = 0;
        if (OrderSetting::where('warehouse', $shop_id)->first()) {
            $warehouse = $shop_id;
        } else {
            if ($setting = OrderSetting::where('shop_id', $shop_id)->first()) {
                if ($setting->warehouse > 0) {
                    $warehouse = $setting->warehouse;
                }
            }
        }

        if (!$warehouse) {
            return;
        }
        if (!$current_shop = Shop::find($shop_id)) {
            return;
        }
        if (!$current_shop->waimai_mt) {
            return;
        }
        $shop_ids = OrderSetting::where('warehouse', $warehouse)->pluck('shop_id')->toArray();
        array_push($shop_ids, $warehouse);
        $this->log_info('需要同步门店ID:', $shop_ids);
        $shops = Shop::select('id', 'shop_name', 'waimai_mt', 'meituan_bind_platform')->whereIn('id', $shop_ids)->get();
        $this->order->load('items');
        $food_data = [];
        if (!empty($this->order->items)) {
            foreach ($this->order->items as $item) {
                if ($item->app_food_code) {
                    $product_params = [
                        'app_poi_code' => $current_shop->waimai_mt,
                        'app_spu_code' => $item->app_food_code,
                    ];
                    $access_token = '';
                    if ($current_shop->meituan_bind_platform == 31) {
                        $mt = app("meiquan");
                        $access_token = $mt->getShopToken($current_shop->waimai_mt);
                    } else {
                        $mt = app("minkang");
                    }
                    if ($access_token) {
                        $product_params['access_token'] = $access_token;
                    }
                    $product_res = $mt->retailGet($product_params);
                    $this->log_info('获取美团商品详情:' . $item->food_name, is_array($product_res) ? $product_res : [$product_res]);
                    if (isset($product_res['data']['skus'])) {
                        $skus_str = $product_res['data']['skus'];
                        $skus_arr = json_decode($skus_str, true);
                        $stock = $skus_arr[0]['stock'];
                        if ($stock > 0) {
                            WmProduct::whereIn('shop_id', $shop_ids)->update([
                                'stock' => $stock
                            ]);
                            $skus[] = [
                                'sku_id' => $product_res['data']['app_spu_code'],
                                'stock' => $stock
                            ];
                            $food_data[] = [
                                'app_spu_code' => $product_res['data']['app_spu_code'],
                                'skus' => $skus,
                            ];
                        }
                    }
                }
            }
        }
        if (!empty($food_data)) {
            foreach ($shops as $shop) {
                if (!$shop->waimai_mt) {
                    continue;
                }
                $access_token = '';
                if ($shop->meituan_bind_platform == 31) {
                    $mt = app("meiquan");
                    $access_token = $mt->getShopToken($shop->waimai_mt);
                } else {
                    $mt = app("minkang");
                }
                $stock_params = [
                    'app_poi_code' => $shop->waimai_mt,
                    'food_data' => json_encode($food_data, JSON_UNESCAPED_UNICODE)
                ];
                if ($access_token) {
                    $stock_params['access_token'] = $access_token;
                }
                $this->log_info('请求参数', $stock_params);
                $res = $mt->retailSkuStock($stock_params);
                $this->log_info('美团返回结果', [$res]);
            }
        }
    }
}
