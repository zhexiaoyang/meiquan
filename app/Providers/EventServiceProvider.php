<?php

namespace App\Providers;

use App\Events\OrderCancel;
use App\Events\OrderCreate;
use App\Listeners\GetRpPicture;
use App\Listeners\MeiTuanLogisticsSync;
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
            GetRpPicture::class,
        ],
        OrderCancel::class => [
            MeiTuanLogisticsSync::class,
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
