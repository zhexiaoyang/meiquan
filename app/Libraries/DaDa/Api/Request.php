<?php

namespace App\Libraries\DaDa\Api;

use App\Libraries\DaDa\Tool;

class Request
{

    private $http;
    private $app_key;
    private $app_secret;
    private $url;

    public function __construct(string $app_key, string $app_secret, string $url)
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
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
            'app_key' => $this->app_key,
            'v' => "1.0",
            "format" => "json",
            'timestamp' => time(),
            'source_id' => '73753',
            'body' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : ''
        ];

        $params['signature'] = Tool::getSign($params, $this->app_secret);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function get(string $method, array $data)
    {
        $params = array_merge($data, [
            'app_id' => $this->app_id,
            'version' => 1,
            'timestamp' => time(),
            'noncestr' => (string) round(11111, 99999),
            'team_token' => '1PKR3D13JCBIMWAW',
        ]);

        $params['sign'] = Tool::getSign($params, $this->app_secret);

        $http = $this->getHttp();

        $response = $http->get($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 100) {
            \Log::info('达达配送Api返回异常', [$result]);
        }

        return $result;
    }
}
