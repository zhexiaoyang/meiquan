<?php

namespace App\Libraries\Feie;

use App\Libraries\Feie\Api\Api;

class Feie
{
    private $order;

    public function __construct()
    {
        $this->order = new Api();
    }

    public function __call($name, $arguments)
    {
        return $this->order->{$name}(...$arguments);
    }
}
