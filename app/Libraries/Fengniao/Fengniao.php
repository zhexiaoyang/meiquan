<?php

namespace App\Libraries\Fengniao;

use App\Libraries\Fengniao\Api\Api;

class Fengniao
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['app_key'], $config['secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
