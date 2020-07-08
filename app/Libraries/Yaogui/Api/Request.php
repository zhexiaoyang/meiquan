<?php

namespace App\Libraries\Yaogui\Api;

class Request
{

    private $http;
    private $appKey;
    private $secret;
    private $url;

    public function __construct(string $appKey, string $secret, string $url)
    {
        $this->appKey = $appKey;
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


    public function post(string $method, array $data)
    {
        ksort($data);

        $params = [
            'accessKey' => $this->appKey,
            'timestamp' => time() * 1000,
            'params' => $data
        ];


        $params['signature'] = $this->generateBusinessSign($params);

        $http = $this->getHttp();

        $response = $http->json($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function generateBusinessSign($params) {
        $seed = 'accessKey=' . $params['accessKey'] . '&params=' . json_encode($params['params'], JSON_UNESCAPED_UNICODE) . '&timestamp=' . $params['timestamp'] . $this->secret;
        \Log::info('message', [$seed]);
        return md5($seed);
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 200) {
            \Log::info('药柜Api返回异常', [$result]);
        }

        return $result;
    }
}