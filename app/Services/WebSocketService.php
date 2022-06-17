<?php

namespace App\Services;

use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Support\Facades\Redis;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
/**
 * @see https://wiki.swoole.com/wiki/page/400.html
 */
class WebSocketService implements WebSocketHandlerInterface
{
    // 声明没有参数的构造函数
    public function __construct()
    {
    }
    public function onOpen(Server $server, Request $request)
    {
        // 在触发onOpen事件之前Laravel的生命周期已经完结，所以Laravel的Request是可读的，Session是可读写的
        \Log::info('New WebSocket connection', [$request->fd, request()->all(), session()->getId(), session('xxx'), session(['yyy' => time()])]);
        // $server->push($request->fd, json_encode(['mes' => 'Welcome to LaravelS']));
        $server->push($request->fd, json_encode(['time' => date('Y-m-d H:i:s'), 'fd' => $request->fd], true));
        // throw new \Exception('an exception');// 此时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
        // Redis::hset('h:feedback', $data['user_id'], $request->fd);
        if (!$user_id = request()->get('user_id')) {
            return;
        }
        $fd = $request->fd;
        if ($fd < 10 && Redis::hlen('h:websocket:note_voice:fd') > $fd) {
            Redis::del('h:websocket:note_voice:user');
            Redis::del('h:websocket:note_voice:fd');
        }
        Redis::hset('h:websocket:note_voice:fd', $fd, $user_id);
        if ($user_fd = Redis::hget('h:websocket:note_voice:user', $user_id)) {
            $fd = $user_fd . ',' . $fd;
        }
        Redis::hset('h:websocket:note_voice:user', $user_id, $fd);

    }
    public function onMessage(Server $server, Frame $frame)
    {
        // $ser = app('swoole');
        // $ser->push($frame->fd, json_encode(['time' => '看来是京东方金黄色的副科级'], true));
        // \Log::info('Received message', [$frame->fd, $frame->data, $frame->opcode, $frame->finish]);
        // $server->push($frame->fd, json_encode(['time' => date('Y-m-d H:i:s'), 'fd' => $frame->fd], true));
        // throw new \Exception('an exception');// 此时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
    }
    public function onClose(Server $server, $fd, $reactorId)
    {
        \Log::info('onClose', [$fd, $reactorId]);
        $user_id = Redis::hget('h:websocket:note_voice:fd', $fd);
        if ($user_id) {
            if ($fds = Redis::hget('h:websocket:note_voice:user', $user_id)) {
                $fd_arr = explode(',', $fds);
                foreach ($fd_arr as $k => $v) {
                    if ($v == $fd) {
                        unset($fd_arr[$k]);
                    }
                }
                Redis::hset('h:websocket:note_voice:user', $user_id, implode(',', $fd_arr));
            }
        }
        Redis::hdel('h:websocket:note_voice:fd', $fd);
        // throw new \Exception('an exception');// 此时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
    }
}
