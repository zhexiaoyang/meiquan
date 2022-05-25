<?php

namespace App\Libraries\ShanSongService\Api;

use App\Models\ShopShipper;
use Illuminate\Support\Facades\Cache;

class Request
{

    protected $http;
    protected $client_id;
    protected $secret;
    protected $url;
    public $access_token;

    public function __construct(string $client_id, string $secret, string $url)
    {
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
            'timestamp' => time(),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ];

        if ($this->access_token) {
            $params['accessToken'] = $this->access_token;
        }

        $params['sign'] = strtoupper($this->signature($params));

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    private function signature($params) {
        $seed = $this->secret . 'accessToken' . $params['accessToken'] . 'clientId' . $params['clientId'] . 'data' . $params['data'] . 'timestamp' . $params['timestamp'];
        return md5($seed);
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['status'] != 200) {
            \Log::info('闪送服务商返回异常', [$result]);
        }

        return $result;
    }
}
