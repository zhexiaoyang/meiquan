<?php

namespace App\Task;

use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Support\Facades\Redis;

class TakeoutOrderVoiceNoticeTask extends Task
{
    public $voice;
    public $user_id;

    // 1 新订单，2 预订单，5 催单，7 退款，9 取消订单
    // 11 骑手接单，14 订单送达，17 配送失败提示
    // 21 超时10分钟未接单，23 超时20分钟未接单， 27 取货30分钟未送达
    // 31 跑腿账户余额不足提示
    public function __construct($voice, $user_id)
    {
        $this->voice = $voice;
        $this->user_id = $user_id;
    }

    public function handle()
    {
        if ($fd_str = Redis::hget('h:websocket:note_voice:user', $this->user_id)) {
            $res = [
                'mes' => 'success',
                'kind' => 'voice',
                'voice' => $this->voice
            ];
            $res = json_encode($res, true);
            $fds = explode(',', $fd_str);
            $server = app('swoole');
            foreach ($fds as $fd) {
                \Log::info("fd:{$fd},res:{$res}");
                $server->push($fd, $res);
            }
        }
    }
}
