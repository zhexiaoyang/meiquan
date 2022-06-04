<?php

namespace App\Libraries\ShunfengService;

use App\Libraries\ShunfengService\Api\Api;

class ShunfengService
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
