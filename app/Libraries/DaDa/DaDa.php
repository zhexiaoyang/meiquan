<?php

namespace App\Libraries\DaDa;

use App\Libraries\DaDa\Api\Api;

class DaDa
{
    private $api;

    public function __construct($config)
    {
        $this->api = new Api($config['app_key'], $config['app_secret'], $config['url'], $config['source_id']);
    }

    public function __call($name, $arguments)
    {
        return $this->api->{$name}(...$arguments);
    }
}
