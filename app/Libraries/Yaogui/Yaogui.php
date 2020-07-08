<?php

namespace App\Libraries\Yaogui;

use App\Libraries\Yaogui\Api\Api;

class Yaogui
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