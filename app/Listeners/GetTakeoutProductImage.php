<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\MedicineDepot;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use App\Traits\GetMedicineImageByStoreID;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GetTakeoutProductImage implements ShouldQueue
{
    use GetMedicineImageByStoreID;
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
                    $image_url = $this->getImageByStoreId($order->platform, $order->from_type, $order->app_poi_code, $app_food_code, $mt_spu_id);
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
}
