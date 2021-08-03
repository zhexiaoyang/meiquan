<?php

namespace App\Libraries\Uu;

use App\Libraries\Uu\Api\Api;

class Uu
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Api($config['app_id'], $config['app_key'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
