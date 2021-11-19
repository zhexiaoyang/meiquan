<?php

namespace App\Libraries\TaoZi\Api;

use App\Libraries\TaoZi\Tool;

class Request
{

    private $http;
    private $access_key;
    private $secret_key;
    private $url;

    public function __construct(string $access_key, string $secret_key, string $url)
    {
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
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
        $timestamp = time();

        $params = [
            'accessKey' => $this->access_key,
            'timestamp' => $timestamp,
        ];

        $params = array_merge($data, $params);

        $params['sign'] = Tool::getSign($params, $this->secret_key);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function post2(string $method, array $data)
    {
        $timestamp = time();

        $params = [
            'thirdOrgID' => $this->access_key,
            "thirdOrgName" => "美全科技",
            'timestamp' => $timestamp,
        ];

        $params = array_merge($data, $params);

        $params['sign'] = Tool::getSign2($params, $this->secret_key);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function get(string $method, array $data)
    {
        $timestamp = time();

        $params = [
            'accessKey' => $this->access_key,
            'timestamp' => $timestamp,
        ];

        $params = array_merge($data, $params);

        $params['sign'] = Tool::getSign($params, $this->secret_key);

        $http = $this->getHttp();

        $response = $http->get($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 0) {
            \Log::info('桃子医院Api返回异常', [$result]);
        }

        return $result;
    }
}
