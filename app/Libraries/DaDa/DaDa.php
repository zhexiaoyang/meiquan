<?php

namespace App\Libraries\DaDa;

use App\Libraries\DaDa\Api\Api;

class DaDa
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['app_key'], $config['app_secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
