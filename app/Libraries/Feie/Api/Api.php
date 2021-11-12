<?php

namespace App\Libraries\Feie\Api;

class Api extends Request
{
    public function print_msg($sn = '', $content = '', $times = 1)
    {
        $data = array(
            'apiname'=>'Open_printMsg',
            'sn' => $sn,
            'content' => $content,
            'times' => $times
        );

        return $this->post('', $data);
    }

    public function print_del($sn = '')
    {
        $data = array(
            'apiname'=>'Open_printerDelList',
            'snlist' => $sn,
        );

        return $this->post('', $data);
    }

    public function print_add($content)
    {
        $data = array(
            'apiname'=>'Open_printerAddlist',
            'printerContent' => $content,
        );

        return $this->post('', $data);
    }
}
