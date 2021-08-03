<?php

namespace App\Libraries\Uu\Api;

use App\Libraries\Uu\Tool;

class Request
{

    private $http;
    private $app_id;
    private $app_key;
    private $url;

    public function __construct(string $app_id, string $app_key, string $url)
    {
        $this->app_id = $app_id;
        $this->app_key = $app_key;
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
            'appid' => $this->app_id,
            'timestamp' => time(),
            'nonce_str' => Tool::ticket(),
            'openid' => '910a0dfd12bb4bc0acec147bcb1ae246',
        ];

        $params = array_merge($data, $params);

        $params['sign'] = Tool::getSign($params, $this->app_key);

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

        $params['sign'] = Tool::getSign($params, $this->app_key);

        $http = $this->getHttp();

        $response = $http->get($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['return_code'] != 'ok') {
            \Log::info('Uu跑腿配送Api返回异常', [$result]);
        }

        return $result;
    }
}
