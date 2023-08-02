<?php

namespace App\Traits;

use App\Libraries\DingTalk\DingTalkRobotNotice;

trait NoticeTool2
{
    public $notice_tool2_prefix = '';

    public function ding_exception($message, $data = [])
    {
        $ding = new DingTalkRobotNotice("c957a526bb78093f61c61ef0693cc82aae34e079f4de3321ef14c881611204c4");
        $ding->sendTextMsg($message . '|' . $this->notice_tool2_prefix . '|' . date("Y-m-d H:i:s"));
    }

    public function ding_error($message, $data = [])
    {
        $ding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
        $ding->sendTextMsg($message . '|' . $this->notice_tool2_prefix . '|错误' . date("Y-m-d H:i:s"));
    }
}
