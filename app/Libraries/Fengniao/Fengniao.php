<?php

namespace App\Libraries\Fengniao;

use App\Libraries\Fengniao\Api\Order;

class Fengniao
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Order($config['app_key'], $config['secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}