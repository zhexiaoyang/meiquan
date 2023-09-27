<?php

namespace App\Task;

use App\Models\User;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Support\Facades\Redis;

class TakeoutImMessageTask extends Task
{
    public $message_id;
    public $app_id;
    public $user_id;
    public $redis_key_fd;
    public $redis_key_user;
    // 是否往其它服务器推送
    public $other;
    public $data;

    //
    public function __construct($message_id, $user_id, $data = [], $other = true)
    {
        $app_id = config('app.app_id');
        $this->app_id = $app_id;
        $this->redis_key_fd = "h{$app_id}:websocket:note_voice:fd";
        $this->redis_key_user = "h{$app_id}:websocket:note_voice:user";
        $this->message_id = $message_id;
        $this->user_id = $user_id;
        $this->other = $other;
        $this->data = $data;
    }

    public function handle()
    {
        \Log::info("WebStock推送IM消息-开始执行");
        // if (!$user = User::find($this->user_id)) {
        //     return;
        // }
        if ($fd_str = Redis::hget($this->redis_key_user, $this->user_id)) {
            $res = [
                'mes' => 'success',
                'kind' => 'im',
                'message_id' => $this->message_id,
                'data' => $this->data,
            ];
            $res = json_encode($res, true);
            $fds = explode(',', $fd_str);
            $server = app('swoole');
            $user_fds = [];
            foreach ($fds as $fd) {
                if ($server->isEstablished($fd)) {
                    $user_fds[] = $fd;
                    \Log::info("fd:{$fd},res:{$res}");
                    $server->push($fd, $res);
                // } else {
                    // 该fd没有连接，删除掉
                    // Redis::hdel($this->redis_key_fd, $fd);
                    // \Log::info("TakeoutOrderVoiceNoticeTask|fd没有链接，删除掉|user_id:{$this->user_id},fd:{$fd}");
                }
            }
            if (empty($user_fds)) {
                // 该user_id没有连接，删除掉
                Redis::hdel($this->redis_key_user, $this->user_id);
                \Log::info("WebStock推送IM消息|user_id没有链接，删除掉|user_id:{$this->user_id}");
            } else {
                if (count($user_fds) < count($fds)) {
                    $new_fd_str = implode(',', $user_fds);
                    Redis::hset($this->redis_key_user, $this->user_id, $new_fd_str);
                    \Log::info("WebStock推送IM消息|user_id的fd有减少，重新赋值|原fd:{$fd_str}，新fd:{$new_fd_str}");
                }
            }
        }
        // 往其它服务器推送
        \Log::info("WebStock推送IM消息|往其它服务器推送|user_id:{$this->user_id}|other:{$this->other}");
        if ($this->other) {
            $urls = config('ps.stock_urls');
            if (!empty($urls)) {
                $params = [
                    'cmd' => 'im',
                    'user_id' => $this->user_id,
                    'message_id' => $this->message_id,
                    'data' => json_encode($this->data, JSON_UNESCAPED_UNICODE)
                ];
                $params_str = http_build_query($params);
                foreach ($urls as $key => $url) {
                    if ($key !== $this->app_id) {
                        file_get_contents($url . '?' . $params_str);
                    }
                }
            }
        }
    }
}
