<?php

namespace App\Libraries\Shansong;

use App\Libraries\Shansong\Api\Api;

class Shansong
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['shop_id'], $config['client_id'], $config['secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}