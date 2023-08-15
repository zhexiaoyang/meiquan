<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;

class TodayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-today';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shops = Shop::where('user_id', '>', 0)->where(function($query) {
            $query->where('waimai_mt', '<>', '')->orWhere('waimai_ele', '<>', '');
        })->get();
        if (!empty($shops)) {
            $minkang = app('minkang');
            $meiquan = app('meiquan');
            $cabyin = app("mtkf");
            $ele = app("ele");
            foreach ($shops as $shop) {
                $mt_shop_name = '';
                if ($shop->waimai_mt && !$shop->mt_shop_name) {
                    if ($shop->meituan_bind_platform == 4) {
                        $mt_res = $minkang->getShops(['app_poi_codes' => $shop->waimai_mt]);
                        $mt_shop_name = $mt_res[0]['name'] ?? '';
                    } elseif ($shop->meituan_bind_platform == 31) {
                        $mt_res = $meiquan->getShops(['app_poi_codes' => $shop->waimai_mt]);
                        $mt_shop_name = $mt_res[0]['name'] ?? '';
                    } elseif ($shop->meituan_bind_platform == 25) {
                        $mt_res = $cabyin->ng_shop_info($shop->id);
                        $mt_shop_name = $mt_res['data']['name'] ?? '';
                    }
                }
                $ele_shop_name = '';
                if ($shop->waimai_ele && !$shop->ele_shop_name) {
                    $data = $ele->shopInfo($shop->waimai_ele);
                    if (isset($data['body']['errno']) && $data['body']['errno'] === 0) {
                        $ele_shop_name = $data['body']['data']['name'] ?? '';
                    }
                }
                $update_data = [];
                if ($mt_shop_name) {
                    $update_data['wm_shop_name'] = $mt_shop_name;
                    $update_data['mt_shop_name'] = $mt_shop_name;
                }
                if ($ele_shop_name) {
                    $update_data['ele_shop_name'] = $ele_shop_name;
                    if (!isset($update_data['wm_shop_name']) && !$shop->wm_shop_name) {
                        $update_data['wm_shop_name'] = $ele_shop_name;
                    }
                }
                if (!empty($update_data)) {
                    Shop::where('id', $shop->id)->update($update_data);
                }
            }
        }
    }
}
