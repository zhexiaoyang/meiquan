<?php

namespace App\Libraries\KuaiDi;

use App\Libraries\KuaiDi\Api\Api;

class KuaiDi
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['key'], $config['secret'], $config['customer'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
