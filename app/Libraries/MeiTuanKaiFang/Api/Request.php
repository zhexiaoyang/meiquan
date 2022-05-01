<?php

namespace App\Libraries\MeiTuanKaiFang\Api;

use App\Libraries\MeiTuanKaiFang\Tool;

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

    public function post(string $method, array $data, $businessId = 2)
    {
        $params = [
            'developerId' => (int) $this->app_id,
            'timestamp' => time(),
            'charset' => 'utf-8',
            'version' => 2,
            'businessId' => $businessId,
        ];

        $params = array_merge($data, $params);

        // $params['sign'] = Tool::get_sign($params, $this->app_key);
        $sign = Tool::get_sign($params, $this->app_key);
        $params['sign'] = $sign;
        $url = $this->url . $method."?sign=".$sign."&".Tool::concat_params($params);

        $http = $this->getHttp();

        $response = $http->post($url, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function get(string $method, array $data)
    {
        $params = [
            'developerId' => $this->app_id,
            'timestamp' => time(),
            'charset' => 'utf-8',
            'version' => 2,
            'businessId' => 2,
        ];

        $params = array_merge($data, $params);

        $sign = Tool::get_sign($params, $this->app_key);

        $http = $this->getHttp();

        $url = $this->url . $method."?sign=".$sign."&".Tool::concat_params($params);

        $response = $http->get($url, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 'OP_SUCCESS') {
            \Log::info('美团开放平台Api返回异常', [$result]);
        }

        return $result;
    }
}
