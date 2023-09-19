<?php

namespace App\Providers;

use App\Events\OrderCancel;
use App\Events\OrderComplete;
use App\Events\OrderCreate;
use App\Events\OrderCreated;
use App\Listeners\DeliveryVoiceReminder;
use App\Listeners\GetRpPicture;
use App\Listeners\GetTakeoutProductImage;
use App\Listeners\MeiTuanLogisticsSync;
use App\Listeners\MeituanPostbackUpdate;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        OrderCreate::class => [
            // 外卖订单-处方单创建完成
            GetRpPicture::class,
        ],
        // 跑腿订单、外卖订单创建成功
        OrderCreated::class => [
            // 获取商品图片
            GetTakeoutProductImage::class,
        ],
        // 跑腿订单取消
        OrderCancel::class => [
            // 同步取消信息到美团
            MeiTuanLogisticsSync::class,
        ],
        // 跑腿订单配送完成
        OrderComplete::class => [
            // 自配回传更新
            MeituanPostbackUpdate::class,
            // 跑腿订单声音提醒
            DeliveryVoiceReminder::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
