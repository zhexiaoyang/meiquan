<?php

namespace App\Jobs;

use Illuminate\Http\Request;
use App\Models\MkOrder;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderToErp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $shop_id;
    public $tries = 5;

    /**
     * SendOrderToErp constructor.
     * @param $shop_id
     * @param MkOrder $order
     */
    public function __construct($data, $shop_id)
    {
        $this->data = $data;
        $this->shop_id = $shop_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $products = json_decode(urldecode($data['detail']), true);

        $http = new Client();
        $detail = [];
        if (!empty($products)) {
            foreach ($products as $product) {
                $detail[] = [
                    "app_food_code" => $product['app_food_code'],
                    "food_name" => $product['food_name'],
                    "upc" => $product['upc'],
                    "price" => (float) $product['price'],
                    "quantity" => $product['quantity'],
                    "unit" => $product['unit'],
                    "spec" => $product['spec'],
                ];
            }
        }
        $data = [
            "shop_id" => $this->shop_id,
            "order_id" => $data['wm_order_id_view'],
            "wm_order_id_view" => $data['wm_order_id_view'],
            "recipient_address" => urldecode($data['recipient_address']),
            "recipient_name" => urldecode($data['recipient_name']) ?? "无名客人",
            "recipient_phone" => $data['recipient_phone'],
            "shipping_fee" => (float) $data['shipping_fee'],
            "total" => (float) $data['total'],
            "original_price" => (float) $data['original_price'],
            "caution" => urldecode($data['caution']),
            "status" => $data['status'],
            "invoice_title" => $data['invoice_title'] ?? '',
            "delivery_time" => $data['delivery_time'],
            "latitude" => (float) $data['latitude'],
            "longitude" => (float) $data['longitude'],
            "day_seq" => $data['day_seq'],
            "detail" => $detail
        ];
        $params = [
            "service_key" => "HXFW_362",
            "hx_parama" => $data
        ];
        try {
            \Log::info("海协ERP推送订单", $params);
            $response = $http->post("http://hxfwgw.drugwebcn.com/gateway/apiEntranceAction!apiEntrance.do", [RequestOptions::JSON => $params]);
            $result = json_decode($response->getBody(), true);
            \Log::info("海协ERP推送订单-返回", [$result]);
        } catch (\Exception $exception) {
            \Log::info("海协ERP推送订单-失败", [$exception->getMessage()]);
        }
    }
}
