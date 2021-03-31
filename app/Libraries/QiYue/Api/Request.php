<?php

namespace App\Libraries\QiYue\Api;

class Request
{

    private $http;
    private $token;
    private $secret;
    private $url;

    public function __construct(string $token, string $secret, string $url)
    {
        $this->token = $token;
        $this->secret = $secret;
        $this->url = $url;
    }

    public function getHttp()
    {
        if (is_null($this->http)) {
            $this->http = new Http();
        }
        return $this->http;
    }


    public function post(string $method, array $params)
    {
        $http = $this->getHttp();
        $timestamp = time() * 1000;
        $nonce = rand(1000, 9999);

        $headers = [
            'x-qys-open-accesstoken' => $this->token,
            'x-qys-open-timestamp' => $timestamp,
            'x-qys-open-nonce' => $nonce,
            'x-qys-open-signature' => md5($this->token . $this->secret . $timestamp . $nonce)
        ];

        $response = $http->json($this->url . $method, $params, $headers);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function get(string $method, array $params)
    {
        $params = array_merge($params, [
            'app_id' => $this->appKey,
            'salt' => rand(1000, 9999)
        ]);

        $signature = $this->signature($params);

        $http = $this->getHttp();

        $url = $this->url.$method."?signature=".$signature."&".$this->concatParams($params);

        $response = $http->get($url);

        $result = json_decode(strval($response->getBody()), true);

        return $this->checkErrorAndThrow($result);
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 0) {
            \Log::info('[契约锁]-[返回异常]', [$result]);
        }

        return $result;
    }
}
