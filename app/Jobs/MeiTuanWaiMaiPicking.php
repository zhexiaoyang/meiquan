<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MeiTuanWaiMaiPicking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $order_id, $ttl = 0)
    {
        $this->delay = $ttl;
        $this->order_id = $order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $meituan = app("minkang");
        $res = $meituan->orderPicking($this->order_id);
        \Log::info("JOB:自动拣货|订单号：{$this->order_id}|操作接单返回信息", $res);
    }
}
