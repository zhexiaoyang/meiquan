<?php


namespace App\Libraries\Meituan;


use App\Libraries\Meituan\Api\Api;

class MeiTuan
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