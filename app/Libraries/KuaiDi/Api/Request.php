<?php

namespace App\Libraries\KuaiDi\Api;

use App\Libraries\KuaiDi\Tool;

class Request
{

    private $http;
    private $key;
    private $secret;
    private $customer;
    private $url;

    public function __construct(string $key, string $secret, string $customer, string $url)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->customer = $customer;
        $this->url = $url;
    }

    public function getHttp()
    {
        if (is_null($this->http)) {
            $this->http = new Http();
        }
        return $this->http;
    }


    public function post(string $method, array $data)
    {
        $params = [
            'customer' => $this->customer,
            'param' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ];

        $params['sign'] = Tool::getSign($data, $this->key, $this->customer);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        return $result;
    }

}
