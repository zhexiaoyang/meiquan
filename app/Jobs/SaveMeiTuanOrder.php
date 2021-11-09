<?php

namespace App\Jobs;

use App\Models\WmOrderItem;
use App\Models\WmOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SaveMeiTuanOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    private $platform;
    private $from_type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, int $platform, int $from_type)
    {
        $this->data = $data;
        $this->platform = $platform;
        $this->from_type = $from_type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;

        $mt_shop_id = $data['app_poi_code'];
        $mt_order_id = $data['wm_order_id_view'];
        $products = json_decode(urldecode($data['detail']), true);

        if (!$mt_order_id || !$mt_shop_id) {
            return false;
        }

        $order_data = [
            "shop_id" => 0,
            "order_id" => $mt_order_id,
            "wm_order_id_view" => $mt_order_id,
            "platform" => $this->platform,
            "from_type" => $this->from_type,
            "app_poi_code" => $mt_shop_id,
            "wm_shop_name" => urldecode($data['wm_poi_name'] ?? ''),
            "recipient_name" => urldecode($data['recipient_name']) ?? "无名客人",
            "recipient_phone" => $data['recipient_phone'],
            "recipient_address" => urldecode($data['recipient_address']),
            "latitude" => $data['latitude'],
            "longitude" => $data['longitude'],
            "shipping_fee" => $data['shipping_fee'],
            "total" => $data['total'],
            "original_price" => $data['original_price'],
            "caution" => urldecode($data['caution']),
            "shipper_phone" => $data['shipper_phone'] ?? "",
            "status" => $data['status'],
            "ctime" => $data['ctime'],
            "utime" => $data['utime'],
            "delivery_time" => $data['delivery_time'],
            "pick_type" => $data['pick_type'] ?? 0,
            "day_seq" => $data['day_seq'] ?? 0,
        ];

        DB::transaction(function () use ($products, $order_data) {
            $items = [];
            $order = WmOrder::query()->create($order_data);
            if (!empty($products)) {
                foreach ($products as $product) {
                    $items[] = [
                        'order_id' => $order->id,
                        'app_food_code' => $product['app_food_code'],
                        'food_name' => $product['food_name'],
                        'unit' => $product['unit'],
                        'upc' => $product['upc'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                        'spec' => $product['spec'],
                    ];
                }
            }
            if (!empty($items)) {
                WmOrderItem::query()->insert($items);
            }
        });
    }
}
