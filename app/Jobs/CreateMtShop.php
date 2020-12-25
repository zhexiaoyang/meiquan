<?php

namespace App\Jobs;

use App\Models\Shop;
use function GuzzleHttp\Psr7\str;
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
        if (!$this->shop->shop_id) {
            $meituan = app("meituan");
            $result = $meituan->shopCreate($this->shop);
            if (isset($result['code']) && $result['code'] == 0) {
                if ($this->shop->status < 10) {
                    // $this->shop->status = 20;
                    $this->shop->save();
                }
            }
        }

        if (!$this->shop->shop_id_fn) {
            $fengniao = app("fengniao");
            $result = $fengniao->createShop($this->shop);
            if (isset($result['code']) && $result['code'] == 200) {
                if ($this->shop->status < 10) {
                    // $this->shop->status = 20;
                    $this->shop->save();
                }
            }
        }

        if (!$this->shop->shop_id_ss) {
            $shansong = app("shansong");
            $result = $shansong->createShop($this->shop);
            if (isset($result['status']) && $result['status'] == 200) {
                // $this->shop->status = 40;
                $this->shop->shop_id_ss = $result['data'];
                $this->shop->save();
            }
        }
    }
}
