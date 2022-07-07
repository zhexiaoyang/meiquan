<?php

namespace App\Jobs;

use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\WmProduct;
use App\Traits\LogTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarehouseStockSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogTool;

    public $warehouse;
    public $products;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($warehouse, $products)
    {
        $this->prefix = '仓库库存同任务:' . $warehouse;
        $this->warehouse = $warehouse;
        $this->products = $products;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shop_ids = OrderSetting::where('warehouse', $this->warehouse)->pluck('shop_id')->toArray();
        array_push($shop_ids, $this->warehouse);
        $this->log_info('需要同步门店ID:', $shop_ids);
        $shops = Shop::select('id', 'shop_name', 'waimai_mt', 'meituan_bind_platform')->whereIn('id', $shop_ids)->get();
        if (empty($shops)) {
            $this->log_info('需要同步门店为空');
        }
        if (empty($this->products)) {
            $this->log_info('需要同步商品为空');
            return;
        }
        $food_data = [];
        foreach ($this->products as $product) {
            $app_food_code = $product['app_food_code'] ?? '';
            $quantity = $product['quantity'] ?? '';
            if ($app_food_code && $quantity) {
                if ($food = WmProduct::where('shop_id', $this->warehouse)->where('app_food_code', $app_food_code)->first()) {
                    $stock = 0;
                    if ($food->stock >= $quantity) {
                        $stock = $food->stock - $quantity;
                    }
                    WmProduct::whereIn('shop_id', $shop_ids)->update([
                        'stock' => $stock
                    ]);
                    $skus[] = [
                        'sku_id' => $food->app_food_code,
                        'stock' => $stock
                    ];
                    $food_data[] = [
                        'app_spu_code' => $food->app_food_code,
                        // 'skus' => json_encode($skus, JSON_UNESCAPED_UNICODE),
                        'skus' => $skus,
                    ];
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
