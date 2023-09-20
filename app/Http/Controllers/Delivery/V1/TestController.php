<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Events\OrderComplete;
use App\Http\Controllers\Controller;
use App\Jobs\SetSelfDeliveryFinishJob;
use App\Task\SetSelfDeliveryFinishTask;
use App\Task\TakeoutOrderVoiceNoticeTask;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test()
    {
        // SetSelfDeliveryFinishJob::dispatch(1, 1, 1);
        // event(new OrderComplete(1, 1, 70));
        Task::deliver(new TakeoutOrderVoiceNoticeTask(14, 1), true);
        // $task = new SetSelfDeliveryFinishTask(4058974, '2023-09-19 12:27:35');
        // $task->delay(10);
        // Task::deliver($task);
        // $server = app('swoole');
        // $server->push(114, json_encode([1,2]));
        // return $this->success($server->isEstablished(114));
    }
}
