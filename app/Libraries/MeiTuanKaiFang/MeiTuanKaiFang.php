<?php

namespace App\Libraries\MeiTuanKaiFang;

use App\Libraries\MeiTuanKaiFang\Api\Api;

class MeiTuanKaiFang
{
    private $api;

    public function __construct($config)
    {
        $this->api = new Api($config['app_id'], $config['app_key'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->api->{$name}(...$arguments);
    }
}
