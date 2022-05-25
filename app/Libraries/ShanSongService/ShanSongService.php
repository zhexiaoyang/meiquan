<?php

namespace App\Libraries\ShanSongService;

use App\Libraries\ShanSongService\Api\Api;

class ShanSongService
{
    // private $config;
    private $api;

    public function __construct($config)
    {
        // $this->config = $config;
        $this->api = new Api($config['client_id'], $config['secret'], $config['url']);
    }

    public function __call($name, $arguments)
    {
        return $this->api->{$name}(...$arguments);
    }
}
