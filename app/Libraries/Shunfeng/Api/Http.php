<?php

namespace App\Libraries\Shunfeng\Api;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as HttpClient;

class Http
{

    protected $client;

    function post($url, $jsonData) {
        Log::debug('顺丰请求参数:', [$jsonData]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $output = curl_exec($ch);
        Log::debug('顺丰响应参数:', [$output]);
        curl_close($ch);
        return $output;
    }

    public function getClient()
    {
        if (!($this->client instanceof HttpClient)) {
            $headers = [
                'content-type' => 'application/json'
            ];
            $this->client = new HttpClient($headers);
        }
        return $this->client;
    }

    public function setClient(HttpClient $client)
    {
        $this->client = $client;
        return $this;
    }
}
