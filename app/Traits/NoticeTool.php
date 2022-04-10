<?php

namespace App\Traits;

use App\Libraries\DingTalk\DingTalkRobotNotice;

trait NoticeTool
{
    public $prefix = '';

    public function ding_exception($message, $data = [])
    {
        $ding = new DingTalkRobotNotice("c957a526bb78093f61c61ef0693cc82aae34e079f4de3321ef14c881611204c4");
        $ding->sendTextMsg($message . '|' . $this->prefix);
    }
}
