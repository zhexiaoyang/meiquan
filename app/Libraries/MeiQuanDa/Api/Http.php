<?php

namespace App\Libraries\MeiQuanDa\Api;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as HttpClient;

class Http
{

    protected $client;

    public function post($url, array $form = [])
    {
        return $this->request('POST', $url, ['form_params' => $form]);
    }

    public function get($url, array $form = [])
    {
        return $this->request('GET', $url, ['query' => $form]);
    }

    public function request($method, $url, $options = [])
    {
        $method = strtoupper($method);
        Log::debug('美全达配送请求参数:', compact('url', 'method', 'options'));
        $response = $this->getClient()->request($method, $url, $options);
        // var_dump($response);die;
        Log::debug('美全达配送响应参数:', [
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
