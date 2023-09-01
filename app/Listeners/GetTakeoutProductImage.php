<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\MedicineDepot;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GetTakeoutProductImage implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderCreated  $event
     * @return void
     */
    public function handle(OrderCreated $event)
    {
        $order_id = $event->wm_order_id;
        $order = WmOrder::select('id','platform','from_type','app_poi_code')->find($order_id);
        if (!$order) {
            return;
        }
        $products = WmOrderItem::select('id', 'order_id', 'upc', 'app_food_code', 'mt_spu_id')->where('order_id', $order_id)->get();
        if ($products->isNotEmpty()) {
            foreach ($products as $product) {
                $app_food_code = $product->app_food_code;
                $mt_spu_id = $product->mt_spu_id;
                $image_url = '';
                $image_from = 2;
                if ($app_food_code && $app_food_code !== 'default') {
                    $image_url = $this->getImage($order->platform, $order->from_type, $order->app_poi_code, $app_food_code, $mt_spu_id);
                    if ($image_url) {
                        $image_from = 1;
                    }
                }
                if (!$image_url && $product->upc) {
                    if ($deopt = MedicineDepot::select('cover')->where('upc', $product->upc)->first()) {
                        $image_url = $deopt->cover;
                    }
                }
                if ($image_url) {
                    $product->update([
                        'image' => $image_url,
                        'image_from' => $image_from,
                    ]);
                }
            }
        }
    }

    public function getImage($platform, $type, $shop_id, $food_id, $mt_spu_id)
    {
        if ($platform == 1) {
            if ($type == 4 || $type == 31) {
                $mt = '';
                if ($type === 4) {
                    $mt = app('minkang');
                } elseif ($type === 31) {
                    $mt = app('meiquan');
                }
                $product = $mt->retail_get($shop_id, $food_id);
                if (!empty($product['data']['picture'])) {
                    $pictures = explode(',', $product['data']['picture']);
                    return $pictures[0] ?? '';
                }
            } elseif ($type == 35) {
                $mt = app('mtkf');
                $product = $mt->wmoper_food_info($food_id, $shop_id);
                if (!empty($product['data']['pictures'])) {
                    $pictures = explode(',', $product['data']['pictures']);
                    return $pictures[0] ?? '';
                }
            }
        } elseif ($platform == 2) {
            $ele = app('ele');
            $product = $ele->skuList($shop_id, '', $mt_spu_id);
            if (!empty($product['body']['data']['list'][0]['photos'])) {
                return $product['body']['data']['list'][0]['photos'][0]['url'] ?? '';
            }
        }

        return false;
    }
}
