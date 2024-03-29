<?php

namespace App\Http\Controllers;

use App\Task\TakeoutImMessageTask;
use App\Task\TakeoutOrderVoiceNoticeTask;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;

class WebStockPushController extends Controller
{
    public function stock(Request $request)
    {
        $cmd = $request->get('cmd');
        $user_id = (int) $request->get('user_id');
        if (!$cmd || !$user_id) {
            return $this->success();
        }
        if ($cmd === 'voice') {
            return $this->voice($user_id, $request->get('voice'));
        } elseif ($cmd === 'im') {
            return $this->im($user_id, $request->get('message_id'), $request->get('data'));
        }
        return $this->success();
    }

    public function voice($user_id, $voice)
    {
        if (!$voice) {
            return $this->success();
        }

        Task::deliver(new TakeoutOrderVoiceNoticeTask((int) $voice, (int) $user_id, false), true);
        return $this->success();
    }

    public function im($user_id, $message_id, $data)
    {
        if (!$message_id) {
            return $this->success();
        }

        Task::deliver(new TakeoutImMessageTask((int) $message_id, (int) $user_id, json_decode($data, true), false), true);
        return $this->success();
    }
}
