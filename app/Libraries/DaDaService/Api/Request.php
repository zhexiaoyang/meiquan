<?php

namespace App\Libraries\DaDaService\Api;

use App\Libraries\DaDaService\Tool;

class Request
{

    protected $http;
    protected $app_key;
    protected $app_secret;
    protected $source_id;
    protected $url;

    public function __construct(string $app_key, string $app_secret, string $url, string $source_id = '118473')
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
        $this->source_id = $source_id;
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
            'source_id' => $this->source_id,
            'body' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : ''
        ];

        $params['signature'] = Tool::getSign($params, $this->app_secret);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function auth_post(string $method, array $params)
    {
        // $params = [
        //     'app_key' => $this->app_key,
        //     'v' => "1.0",
        //     "format" => "json",
        //     'timestamp' => time(),
        //     'source_id' => '118473',
        //     'body' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : ''
        // ];

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
            'app_id' => $this->app_key,
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

    public function auth_get(string $method, array $params)
    {
        $params['sign'] = Tool::getSignAuth($params, $this->app_secret);

        $http = $this->getHttp();

        $response = $http->get($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 0) {
            \Log::info('达达服务商配送Api返回异常', [$result]);
        }

        return $result;
    }
}
