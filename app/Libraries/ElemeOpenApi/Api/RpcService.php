<?php

namespace App\Libraries\ElemeOpenApi\Api;

use App\Libraries\ElemeOpenApi\Config\Config;
use App\Libraries\ElemeOpenApi\Protocol\RpcClient;

class RpcService
{
    protected $client;

    public function __construct($token, Config $config)
    {
        $this->client = new RpcClient($token, $config);
    }
}
