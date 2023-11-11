<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Models\ShopRestLog;
use App\Models\User;
use App\Traits\NoticeTool2;
use App\Traits\SmsTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class StoreRestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SmsTool, NoticeTool2;

    public $shop_id;
    public $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shop_id, $type = 1)
    {
        $this->shop_id = $shop_id;
        // 1 置休，2 营业
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shop = Shop::select('id','user_id','waimai_mt','shop_name','mt_shop_name', 'meituan_bind_platform','yunying_status')->find($this->shop_id);
        if (!$shop) {
            return;
        }
        if (!$shop->yunying_status) {
            return;
        }
        // if (Redis::get('delay_close_time:' . $shop->id)) {
        //     return;
        // }
        $data = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->shop_name,
            'wm_shop_name' => $shop->mt_shop_name ?: $shop->shop_name,
            'type' => $this->type,
            'status' => 0,
            'error' => '',
        ];
        if ($delay_time = Redis::get('delay_close_time:' . $shop->id)){
            $log = ShopRestLog::where('shop_id', $shop->id)->where('error', '管理员操作延时置休')->where('created_at', '<', date("Y-m-d H:i:s", $delay_time + 86400))->first();
            if (!$log) {
                $data['error'] = '管理员操作延时置休';
                ShopRestLog::create($data);
            }
            return ;
        }
        if ($shop->meituan_bind_platform === 4) {
            $mt = app('minkang');
        } else if ($shop->meituan_bind_platform === 31) {
            $mt = app('meiquan');
        } else {
            $data['error'] = '未绑定民康或者闪购，无法操作';
            ShopRestLog::create($data);
            return ;
        }
        $online_shop = $mt->getShops(['app_poi_codes' => $shop->waimai_mt]);
        if (isset($online_shop['data'][0]['open_level'])) {
            if ($this->type === 1) {
                // 操作门店置休
                if ($online_shop['data'][0]['open_level'] == 1) {
                    // 判断门店是营业状态-操作置休
                    $rest_shop = $mt->shopClose($shop->waimai_mt, $shop->meituan_bind_platform === 31);
                    if (isset($rest_shop['data']) && $rest_shop['data'] === 'ok') {
                        $data['status'] = 1;
                        ShopRestLog::create($data);
                        $this->ding_error("门店:{$shop->shop_name},ID:{$shop->waimai_mt}，休息成功");
                        if ($this->type === 1) {
                            if ($user = User::find($shop->user_id)) {
                                $this->restShopSms($user->phone, $user->operate_money);
                            }
                        }
                        return ;
                    } else {
                        $data['error'] = $online_shop['msg'] ?? '操作失败';
                        \Log::info('自动置休门店-置休失败', [$online_shop]);
                        ShopRestLog::create($data);
                    }
                }
            } else {
                // 操作门店营业
                if ($online_shop['data'][0]['open_level'] == 3) {
                    // 判断门店是休息状态-操作营业
                    $rest_shop = $mt->shopOpen($shop->waimai_mt, $shop->meituan_bind_platform === 31);
                    if (isset($rest_shop['data']) && $rest_shop['data'] === 'ok') {
                        $data['status'] = 1;
                        ShopRestLog::create($data);
                        $this->ding_error("门店:{$shop->shop_name},ID:{$shop->waimai_mt}，营业成功");
                        return ;
                    } else {
                        $data['error'] = $online_shop['msg'] ?? '操作失败';
                        \Log::info('自动置休门店-置休失败', [$online_shop]);
                        ShopRestLog::create($data);
                    }
                }
            }
        } else {
            $data['error'] = $online_shop['msg'] ?? '获取门店营业状态失败';
            \Log::info('自动置休门店-获取门店营业状态失败', [$online_shop]);
            ShopRestLog::create($data);
        }
    }
}
