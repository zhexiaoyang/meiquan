<?php


namespace App\Libraries\Meituan\Api;


class Api
{

    private $http;
    private $appKey;
    private $secret;

    const URL = 'https://peisongopen.meituan.com/api/';

    public function __construct(string $appKey, string $secret)
    {
        $this->appKey = $appKey;
        $this->secret = $secret;
    }


    public function getHttp()
    {
        if (is_null($this->http)) {
            $this->http = new Http();
        }
        return $this->http;
    }


    public function request(string $method, array $params)
    {
        $params = array_merge($params, [
            'appkey' => $this->appKey,
            'timestamp' => time(),
            'version' => '1.0',
        ]);

        $params['sign'] = $this->signature($params);

        $http = $this->getHttp();

        $response = $http->post(self::URL . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }


    public function signature(array $params)
    {
        ksort($params);

        $waitSign = '';
        foreach ($params as $key => $item) {
            if ($item !== '') {
                $waitSign .= $key.$item;
            }
        }

        return strtolower(sha1(str_replace(" ","", $this->secret).$waitSign));
    }


    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 0) {
//            throw new MeituanDispatchException($result['message'], $result['code']);
        }
    }
}