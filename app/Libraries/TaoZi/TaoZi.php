<?php

namespace App\Libraries\TaoZi;

use App\Libraries\TaoZi\Api\Api;

class TaoZi
{
    private $order;

    public function __construct($config)
    {
        $this->order = new Api($config['access_key'], $config['secret_key'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
