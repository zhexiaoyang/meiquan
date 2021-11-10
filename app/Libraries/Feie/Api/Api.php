<?php

namespace App\Libraries\Feie\Api;

class Api extends Request
{
    public function print_msg($sn = '', $content = '', $times = 2)
    {
        $data = array(
            'sn' => $sn,
            'content' => $content,
            'times' => 2
        );

        return $this->post('', $data);
    }
}
