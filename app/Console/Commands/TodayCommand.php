<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\WmOrder;
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
        // 获取处方图片
        $orders = WmOrder::select('id', 'order_id', 'from_type')->where('is_prescription', 1)->where('rp_picture', '')->where('created_at', '>', date("Y-m-d", time()-86400))->get();
        $this->info("今天任务-处方单数量：" . $orders->count());
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $name = '';
                $picture_url = '';
                if ($order->platform === 1) {
                    // 美团外卖
                    if ($order->from_type !== 4 && $order->from_type !== 31) {
                        return ;
                    }
                    if ($order->from_type === 4) {
                        $meituan = app('minkang');
                    } else {
                        $meituan = app('meiquan');
                    }

                    $picture_res = $meituan->rp_picture_list($order->app_poi_code, $order->order_id, $order->from_type);
                    // \Log::info('aaa', [$picture_res]);
                    if (!empty($picture_res['data'][0]['rp_url_list'][0])) {
                        $picture_url = $picture_res['data'][0]['rp_url_list'][0];
                    }
                    $name = "{$order->order_id}.png";
                } elseif ($order->platform === 2) {
                    // 饿了么
                    $ele = app('ele');
                    $picture_res = $ele->rpPictureList($order->order_id);
                    \Log::info('aa', [$picture_res]);
                    if (!empty($picture_res['body']['data'][0])) {
                        $picture_url = $picture_res['body']['data'][0];
                    }
                    $name = "{$order->order_id}.pdf";
                }

                if ($picture_url && $name) {
                    $this->info("今天任务-获取处方图片，获取到处方图片。{$order->order_id}");
                    $oss = app('oss');
                    $dir = config('aliyun.rp_dir').date('Ym/d/');
                    try {
                        $content = file_get_contents($picture_url);
                    } catch (\Exception $exception) {
                        $this->ding_error("处方获取图片内容出错|{$order->id}|{$order->order_id}|{$picture_url}");
                    }
                    if (isset($content)) {
                        $res = $oss->putObject(config('aliyun.bucket'), $dir.$name, $content);
                        if (!empty($res['info'])) {
                            $url = 'https://img.meiquanda.com/' . $dir . $name;
                            WmOrder::where('id', $order->id)->update([
                                'rp_picture' => $url
                            ]);
                        }
                    }
                } else {
                    // TODO2, 暂未获取到处方信息
                    \Log::info("今天任务-获取处方图片，暂未获取到处方订单。{$order->order_id}");
                    $this->info("今天任务-获取处方图片，暂未获取到处方订单。{$order->order_id}");
                }
            }
        }
        // 获取外卖门店名称
        $shops = Shop::where('user_id', '>', 0)->where(function($query) {
            $query->where('waimai_mt', '<>', '')->orWhere('waimai_ele', '<>', '');
        })->get();
        $this->info("今天任务-外卖门店名称数量：" . $shops->count());
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
                        // $mt_shop_name = $mt_res['data'][0]['name'] ?? '';
                    } elseif ($shop->meituan_bind_platform == 31) {
                        $mt_res = $meiquan->getShops(['app_poi_codes' => $shop->waimai_mt]);
                        // $mt_shop_name = $mt_res['data'][0]['name'] ?? '';
                    } elseif ($shop->meituan_bind_platform == 25) {
                        $mt_res = $cabyin->ng_shop_info($shop->id);
                        // $mt_shop_name = $mt_res['data'][0]['name'] ?? '';
                    };
                    $mt_shop_name = $mt_res['data'][0]['name'] ?? '';
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
