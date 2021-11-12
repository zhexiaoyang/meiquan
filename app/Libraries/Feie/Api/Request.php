<?php

namespace App\Libraries\Feie\Api;

use App\Libraries\Feie\Tool;

class Request
{

    private $http;
    private $key;
    private $user;
    private $url;

    public function __construct()
    {
        $this->key = 'mGCV8z29J3qTUJdp';
        $this->user = '813785245@qq.com';
        $this->url = 'http://api.feieyun.cn/Api/Open/';
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
        $time = time();

        $params = [
            'user' => $this->user,
            'stime' => $time,
            'sig' => $this->signature($time),
        ];

        $params = array_merge($data, $params);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        return $result;
    }

    public function signature($time)
    {
        return sha1($this->user.$this->key.$time);
    }
}
