<?php

namespace App\Jobs;

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

    protected $order;
    protected $shop_id;

    /**
     * SendOrderToErp constructor.
     * @param $shop_id
     * @param MkOrder $order
     */
    public function __construct($shop_id, MkOrder $order)
    {
        $this->order = $order;
        $this->shop_id = $shop_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $http = new Client();
        $detail = [];
        $items = $this->order->items;
        if (!empty($items)) {
            foreach ($items as $item) {
                $detail[] = [
                    "app_food_code" => $item->app_food_code,
                    "food_name" => $item->food_name,
                    "upc" => $item->upc,
                    "quantity" => $item->quantity,
                    "unit" => $item->unit,
                    "spec" => $item->spec,
                ];
            }
        }
        $data = [
            "shop_id" => $this->shop_id,
            "order_id" => $this->order->order_id,
            "wm_order_id_view" => $this->order->wm_order_id_view,
            "recipient_address" => $this->order->recipient_address,
            "recipient_name" => $this->order->recipient_name,
            "recipient_phone" => $this->order->recipient_phone,
            "shipping_fee" => (float) $this->order->shipping_fee,
            "total" => (float) $this->order->total,
            "original_price" => (float) $this->order->original_price,
            "caution" => $this->order->caution,
            "status" => $this->order->status,
            "invoice_title" => $this->order->invoice_title ?? "",
            "delivery_time" => $this->order->delivery_time,
            "latitude" => (float) $this->order->latitude,
            "longitude" => (float) $this->order->longitude,
            "day_seq" => $this->order->day_seq,
            "detail" => $detail
        ];
        $params = [
            "service_key" => "HXFW_362",
            "hx_parama" => $data
        ];
        \Log::info("海协ERP推送订单", $params);
        $response = $http->post("http://hxfwgw.drugwebcn.com/gateway/apiEntranceAction!apiEntrance.do", [RequestOptions::JSON => $params]);
        $result = json_decode($response->getBody(), true);
        \Log::info("海协ERP推送订单-返回", [$result]);
    }
}
