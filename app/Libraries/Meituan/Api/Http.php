<?php

namespace App\Libraries\Meituan\Api;

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
        return $this->request('GET', $url, $form);
    }

    public function request($method, $url, $options = [])
    {
        $method = strtoupper($method);
//        $options = array_merge(self::$defaults, $options);
//         Log::debug('美团配送请求参数:', compact('url', 'method', 'options'));
//        $options['handler'] = $this->getHandler();
        $response = $this->getClient()->request($method, $url, $options);
        // Log::debug('美团配送响应参数:', [
        //     'Status'  => $response->getStatusCode(),
        //     // 'Reason'  => $response->getReasonPhrase(),
        //     // 'Headers' => $response->getHeaders(),
        //     'Body'    => strval($response->getBody()),
        // ]);
        return $response;
    }

    public function getClient()
    {
        if (!($this->client instanceof HttpClient)) {
            $this->client = new HttpClient();
        }
        return $this->client;
    }

    public function setClient(HttpClient $client)
    {
        $this->client = $client;
        return $this;
    }
}
