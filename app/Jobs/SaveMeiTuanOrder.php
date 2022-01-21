<?php

namespace App\Jobs;

use App\Models\WmOrderItem;
use App\Models\WmOrder;
use App\Models\WmPrinter;
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
    private $shop_id;
    private $g_status;
    private $g_no;
    private $g_error;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, int $platform, int $from_type, int $shop_id, int $g_status = 1, string $g_no = '', string $g_error = '')
    {
        $this->data = $data;
        // 平台
        $this->platform = $platform;
        // 来源
        $this->from_type = $from_type;
        // 美全门店ID
        $this->shop_id = $shop_id;
        // 药柜订单状态
        $this->g_status = $g_status;
        // 药柜订单号
        $this->g_no = $g_no;
        $this->g_error = $g_error;
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

        $status_filter = [1 => 1, 2 => 1, 4 => 4, 8 => 18, 9 => 30];

        $logistics_code = isset($data['logistics_code']) ? intval($data['logistics_code']) : 0;

        if ($logistics_code > 0) {
            if ($logistics_code === 1001) {
                $logistics_code = 1;
            }
            if ($logistics_code === 2002) {
                $logistics_code = 2;
            }
            if ($logistics_code === 3001) {
                $logistics_code = 3;
            }
        }

        $order_data = [
            "shop_id" => $this->shop_id,
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
            "status" => $status_filter[$data['status']] ?? 4,
            "ctime" => $data['ctime'],
            "utime" => $data['utime'],
            "delivery_time" => $data['delivery_time'],
            "pick_type" => $data['pick_type'] ?? 0,
            "day_seq" => $data['day_seq'] ?? 0,
            "logistics_code" => $logistics_code,
            "ware_order_id" => $this->g_no,
            "ware_status" => $this->g_status,
            "ware_error" => $this->g_error,
            "ware_take_code" => substr($mt_order_id, -6),
        ];

        $order = DB::transaction(function () use ($products, $order_data) {
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

            return $order;
        });

        if ($print = WmPrinter::where('shop_id', $this->shop_id)->first()) {
            dispatch(new PrintWaiMaiOrder($order, $print));
        }
    }
}
