<?php

namespace App\Libraries\MeiQuanDa;

use App\Libraries\MeiQuanDa\Api\Api;

class MeiQuanDa
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['app_id'], $config['app_secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
