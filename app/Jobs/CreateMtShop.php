<?php

namespace App\Jobs;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateMtShop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shop;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $meituan = app("meituan");
        $params = [
            'shop_id' => $this->shop->shop_id,
            'shop_name' => $this->shop->shop_name,
            'category' => $this->shop->category,
            'second_category' => $this->shop->second_category,
            'contact_name' => $this->shop->contact_name,
            'contact_phone' => $this->shop->contact_phone,
            'shop_address' => $this->shop->shop_address,
            'shop_lng' => $this->shop->shop_lng * 1000000,
            'shop_lat' => $this->shop->shop_lat * 1000000,
            'coordinate_type' => $this->shop->coordinate_type,
            'delivery_service_codes' => "4012",
            'business_hours' => json_encode($this->shop->business_hours),
        ];
        $result = $meituan->shopCreate($params);
        if (!isset($result['code']) || $result['code'] != 0) {
            $this->shop->status = 0;
            $this->shop->save();
        }
    }
}
