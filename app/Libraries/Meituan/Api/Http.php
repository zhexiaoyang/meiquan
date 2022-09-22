<?php

namespace App\Libraries\Meituan\Api;

use App\Libraries\DingTalk\DingTalkRobotNotice;
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

        try {
            $method = strtoupper($method);
            // $options = array_merge(self::$defaults, $options);
            // Log::debug('美团配送请求参数:', compact('url', 'method', 'options'));
            // $options['handler'] = $this->getHandler();
            $response = $this->getClient()->request($method, $url, $options);
            // Log::debug('美团配送响应参数:', [
            //     'Status'  => $response->getStatusCode(),
            //     // 'Reason'  => $response->getReasonPhrase(),
            //     // 'Headers' => $response->getHeaders(),
            //     'Body'    => strval($response->getBody()),
            // ]);
            return $response;
        } catch (\Exception $e) {
            $ding = new DingTalkRobotNotice("c957a526bb78093f61c61ef0693cc82aae34e079f4de3321ef14c881611204c4");
            $ding->sendTextMsg("美团请求接口异常|{$e->getMessage()}|{$method}|{$url}|" . json_encode($options, JSON_UNESCAPED_UNICODE));
        }
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
