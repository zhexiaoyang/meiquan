<?php

namespace App\Libraries\Ele\Api;

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
        $params = [
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'cmd' => $method,
            'encrypt' => 'aes',
            'source' => $this->appKey,
            'timestamp' => time(),
            'version' => 3,
            'ticket' => Tool::ticket(),
        ];

        $params['sign'] = Tool::getSign($params, $this->secret);

        $http = $this->getHttp();

        $url_data = Tool::argSort($params);
        $url_data = Tool::createLinkstring($url_data);

        $response = $http->json($this->url . '?' . $url_data, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function get(string $method, array $params)
    {
        // $params = array_merge($params, [
        //     'app_id' => $this->appKey,
        //     'salt' => rand(1000, 9999)
        // ]);
        //
        // $signature = $this->signature($params);
        //
        // $http = $this->getHttp();
        //
        // $url = $this->url.$method."?signature=".$signature."&".$this->concatParams($params);
        //
        // $response = $http->get($url);
        //
        // $result = json_decode(strval($response->getBody()), true);
        //
        // return $this->checkErrorAndThrow($result);
    }

    private function checkErrorAndThrow($result)
    {
        if (!empty($result['body']['errno']) && $result['body']['errno'] === 0) {
            \Log::info('饿了么Api返回异常', [$result]);
        }

        return $result;
    }
}
