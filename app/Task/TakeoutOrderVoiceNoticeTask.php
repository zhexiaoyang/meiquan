<?php

namespace App\Task;

use App\Models\User;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Support\Facades\Redis;

class TakeoutOrderVoiceNoticeTask extends Task
{
    public $voice;
    public $app_id;
    public $user_id;
    public $redis_key_fd;
    public $redis_key_user;
    // 是否往其它服务器推送
    public $other;

    // 1 新订单，2 预订单，5 催单，7 退款，9 取消订单
    // 11 骑手接单，14 订单送达，17 配送失败提示(未)
    // 21 超时10分钟未接单（未），23 超时20分钟未接单（未）， 27 取货30分钟未送达（未）
    // 31 跑腿账户余额不足提示（未）
    //
    public function __construct($voice, $user_id, $other = true)
    {
        $app_id = config('app.app_id');
        $this->app_id = $app_id;
        $this->redis_key_fd = "h{$app_id}:websocket:note_voice:fd";
        $this->redis_key_user = "h{$app_id}:websocket:note_voice:user";
        $this->voice = $voice;
        $this->user_id = $user_id;
        $this->other = $other;
    }

    public function handle()
    {
        if (!$user = User::find($this->user_id)) {
            return;
        }
        if (!$user->voice_status) {
            \Log::info("用户关闭声音提醒：" . $this->user_id);
            return;
        }
        if ($fd_str = Redis::hget($this->redis_key_user, $this->user_id)) {
            $res = [
                'mes' => 'success',
                'kind' => 'voice',
                'voice' => $this->voice
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
                } else {
                    // 该fd没有连接，删除掉
                    Redis::hdel($this->redis_key_fd, $fd);
                    \Log::info("TakeoutOrderVoiceNoticeTask|fd没有链接，删除掉|user_id:{$this->user_id},fd:{$fd}");
                }
            }
            if (empty($user_fds)) {
                // 该user_id没有连接，删除掉
                Redis::hdel($this->redis_key_user, $fd);
                \Log::info("TakeoutOrderVoiceNoticeTask|user_id没有链接，删除掉|user_id:{$this->user_id}");
            } else {
                if (count($user_fds) < count($fds)) {
                    $new_fd_str = implode(',', $user_fds);
                    Redis::hset($this->redis_key_user, $this->user_id, $new_fd_str);
                    \Log::info("TakeoutOrderVoiceNoticeTask|user_id的fd有减少，重新赋值|原fd:{$fd_str}，新fd:{$new_fd_str}");
                }
            }
        }
        // 往其它服务器推送
        \Log::info("TakeoutOrderVoiceNoticeTask|往其它服务器推送|user_id:{$this->user_id}|other:{$this->other}");
        if ($this->other) {
            $urls = config('ps.stock_urls');
            if (!empty($urls)) {
                $params = [
                    'cmd' => 'voice',
                    'user_id' => $this->user_id,
                    'voice' => $this->voice,
                ];
                $params_str = http_build_query($params);
                foreach ($urls as $key => $url) {
                    if ($key !== $this->app_id) {
                        file_get_contents($url . '?' . $params_str);
                    }
                }
            }
        }
        // 阿振账号提醒
        // if ($fd_str = Redis::hget($this->redis_key_user, 1)) {
        //     $fds = explode(',', $fd_str);
        //     $server = app('swoole');
        //     foreach ($fds as $fd) {
        //         $res1 = [
        //             'mes' => 'success',
        //             'kind' => 'voice',
        //             'voice' => $this->voice,
        //             'date' => date("Y-m-d H:i:s"),
        //             'fd' => $fd
        //         ];
        //         $res1 = json_encode($res1, true);
        //         $server->push($fd, $res1);
        //     }
        // }
        // 书哥账号提醒
        // if (!$user = User::find(32)) {
        //     return;
        // }
        // if (!$user->voice_status) {
        //     \Log::info("用户关闭声音提醒：" . 32);
        //     return;
        // }
        // if ($fd_str = Redis::hget($this->redis_key_user, 32)) {
        //     $res2 = [
        //         'mes' => 'success',
        //         'kind' => 'voice',
        //         'voice' => $this->voice
        //     ];
        //     $res2 = json_encode($res2, true);
        //     $fds = explode(',', $fd_str);
        //     $server = app('swoole');
        //     foreach ($fds as $fd) {
        //         \Log::info("fd:{$fd},res:{$res2}");
        //         $server->push($fd, $res2);
        //     }
        // }
    }
}
