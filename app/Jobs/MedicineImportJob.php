<?php

namespace App\Jobs;

use App\Models\Medicine;
use App\Models\MedicineDepot;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MedicineImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $medicine;
    public $shop_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $shop_id, array $medicine)
    {
        $this->shop_id = $shop_id;
        $this->medicine = $medicine;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $upc = $this->medicine['upc'];
        $price = $this->medicine['price'];
        $stock = $this->medicine['stock'];
        $cost = $this->medicine['guidance_price'];

        if ($medicine = Medicine::where('upc', $upc)->where('shop_id', $this->shop_id)->first()) {
            $medicine->update([
                'price' => $price,
                'stock' => $stock,
                'guidance_price' => $cost,
            ]);
            if ($shop = Shop::find($this->shop_id)) {
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                }
                if ($meituan !== null && $shop->waimai_mt) {
                    $params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $medicine->upc,
                        'price' => $price,
                        'stock' => $stock,
                    ];
                    if ($shop->meituan_bind_platform == 31) {
                        $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $res = $meituan->medicineUpdate($params);
                    \Log::info("res", [$res]);
                }
            }
        } else {
            if ($depot = MedicineDepot::where('upc', $upc)->first()) {
                \Log::info('upc3:' . $upc);
                $medicine_arr = [
                    'shop_id' => $this->shop_id,
                    'name' => $depot->name,
                    'upc' => $depot->upc,
                    'brand' => $depot->brand,
                    'spec' => $depot->spec,
                    'price' => $price,
                    'stock' => $stock,
                    'guidance_price' => $cost,
                    'depot_id' => $depot->id,
                ];
            } else {
                $name = $this->medicine['name'];
                $medicine_arr = [
                    'shop_id' => $this->shop_id,
                    'name' => $name,
                    'upc' => $upc,
                    'brand' => '',
                    'spec' => '',
                    'price' => $price,
                    'stock' => $stock,
                    'guidance_price' => $cost,
                    'depot_id' => 0,
                ];
            }
            Medicine::create($medicine_arr);
        }
    }
}
