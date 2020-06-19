<?php


namespace App\Libraries\Shansong\Api;


class Request
{

    private $http;
    private $shop_id;
    private $client_id;
    private $secret;
    private $url;

    public function __construct(string $shop_id, string $client_id, string $secret, string $url)
    {
        $this->shop_id = $shop_id;
        $this->client_id = $client_id;
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
            'clientId' => $this->client_id,
            'shopId' => $this->shop_id,
            'timestamp' => time(),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ];

        $params['sign'] = strtoupper($this->signature($params));

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function signature($params) {
        $seed = $this->secret . 'clientId' . $params['clientId'] . 'data' . $params['data'] . 'shopId' . $params['shopId'] . 'timestamp' . $params['timestamp'];
        return md5($seed);
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['status'] != 200) {
            \Log::info('闪送Api返回异常', [$result]);
        }

        return $result;
    }
}