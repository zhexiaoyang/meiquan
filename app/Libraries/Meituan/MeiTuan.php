<?php


namespace App\Libraries\Meituan;


use App\Libraries\Meituan\Api\Order;

class MeiTuan
{
    private $config;
    private $order;

    public function __construct($config)
    {
        $this->config = $config;
        $this->order = new Order($config['app_key'], $config['secret']);
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}