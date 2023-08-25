<?php

namespace App\Listeners;

use App\Events\OrderComplete;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MeituanPostbackUpdate
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderComplete  $event
     * @return void
     */
    public function handle(OrderComplete $event)
    {
        //
    }
}
