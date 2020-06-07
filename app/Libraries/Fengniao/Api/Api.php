<?php


namespace App\Libraries\Fengniao\Api;


class Api
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
            'app_id' => $this->appKey,
            'salt' => rand(1000, 9999),
            'data' => urlencode(json_encode($data))
        ];

        $params['signature'] = $this->generateBusinessSign($params);

        $http = $this->getHttp();

        $response = $http->json($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function get(string $method, array $params)
    {
        $params = array_merge($params, [
            'app_id' => $this->appKey,
            'salt' => rand(1000, 9999)
        ]);

        $signature = $this->signature($params);

        $http = $this->getHttp();

        $url = $this->url.$method."?signature=".$signature."&".$this->concatParams($params);

        $response = $http->get($url);

        $result = json_decode(strval($response->getBody()), true);

        return $this->checkErrorAndThrow($result);
    }

    private function concatParams($params) {
        $pairs = array();
        foreach($params as $key=>$val) {
            array_push($pairs, $key . '=' . $val);
        }
        return join('&', $pairs);
    }

    private function generateBusinessSign($params) {
        $seed = 'app_id=' . $params['app_id'] . '&access_token=' . fengNiaoToken()
            . '&data=' . $params['data'] . '&salt=' . $params['salt'];
        return md5($seed);
    }

    private function signature($params) {
        $seed = 'app_id=' . $params['app_id'] . '&salt=' . $params['salt'] . '&secret_key=' . $this->secret;
        return md5(urlencode($seed));
    }

    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 200) {
            \Log::info('蜂鸟Api返回异常', [$result]);
        }

        return $result;
    }
}