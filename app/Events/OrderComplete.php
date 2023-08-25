<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderComplete
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shop_id;
    public $date;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(int $shop_id, string $date)
    {
        $this->date = $date;
        $this->shop_id = $shop_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
