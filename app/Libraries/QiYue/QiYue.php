<?php

namespace App\Libraries\QiYue;

use App\Libraries\QiYue\Api\Api;

class QiYue
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['token'], $config['secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
