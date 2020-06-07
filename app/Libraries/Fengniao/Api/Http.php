<?php

namespace App\Libraries\Fengniao\Api;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as HttpClient;

class Http
{

    protected $client;

    // public static function doPost($url, $param)
    // {
    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, $url);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_HEADER, false);
    //     curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);     //  不进行ssl 认证
    //     curl_setopt($ch, CURLOPT_POST, true);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($param));
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: Application/json'));
    //     $result = curl_exec($ch);
    //     curl_close($ch);
    //     return $result;
    // }

    public function json($url, array $form = [])
    {
        return $this->request('POST', $url, ['json' => $form]);
    }

    public function get($url, array $form = [])
    {
        return $this->request('GET', $url, $form);
    }

    public function request($method, $url, $options = [])
    {
        $method = strtoupper($method);
        Log::debug('蜂鸟配送请求参数:', compact('url', 'method', 'options'));
        $response = $this->getClient()->request($method, $url, $options);
        // var_dump($response);die;
        Log::debug('蜂鸟送响应参数:', [
            'Status'  => $response->getStatusCode(),
            'Reason'  => $response->getReasonPhrase(),
            // 'Headers' => $response->getHeaders(),
            'Body'    => strval($response->getBody()),
        ]);
        return $response;
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