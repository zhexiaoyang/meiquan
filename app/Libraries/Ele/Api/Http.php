<?php

namespace App\Libraries\Ele\Api;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as HttpClient;

class Http
{

    protected $client;

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
        // Log::debug('饿了么配送请求参数:', compact('url', 'method', 'options'));
        $response = $this->getClient()->request($method, $url, $options);
        // var_dump($response);die;
        // Log::debug('饿了么送响应参数:', [
        //     'Status'  => $response->getStatusCode(),
        //     'Reason'  => $response->getReasonPhrase(),
        //     // 'Headers' => $response->getHeaders(),
        //     'Body'    => strval($response->getBody()),
        // ]);
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
