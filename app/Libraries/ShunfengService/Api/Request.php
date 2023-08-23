<?php

namespace App\Libraries\ShunfengService\Api;

class Request
{

    private $http;
    protected $app_id;
    private $app_key;
    private $url;

    public function __construct(string $app_id, string $app_key, string $url)
    {
        $this->app_id = $app_id;
        $this->app_key = $app_key;
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
        $http = $this->getHttp();

        $post_data = array_merge([
            'dev_id' => $this->app_id,
            'push_time' => time(),
            'version' => 17
        ], $data);

        $post_json = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        $signChar = $post_json."&{$this->app_id}&{$this->app_key}";
        $sign     =  base64_encode(MD5($signChar));

        $res =  $http->post($this->url . $method . '?sign=' . $sign, $post_json);

        $result = json_decode($res, true);

        // if (!isset($result['error_code']) || $result['error_code'] !== 0) {
        //     Log::debug('顺丰响应异常:', [$res]);
        //     return $result['result'] ?? [];
        // }
        return $result;
    }
}
