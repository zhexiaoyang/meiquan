<?php


namespace App\Libraries\Meituan\Api;


class Request
{

    public $http;
    public $appKey;
    public $secret;
    public $url;

    // const URL = 'https://peisongopen.meituan.com/api/';

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


    public function request(string $method, array $params)
    {
        $params = array_merge($params, [
            'appkey' => $this->appKey,
            'timestamp' => time(),
            'version' => '1.0',
        ]);

        $params['sign'] = $this->signature($params);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        $result = json_decode(strval($response->getBody()), true);

        $this->checkErrorAndThrow($result);

        return $result;
    }

    public function request_post(string $method, array $params)
    {
        $result = [];

        $params = array_merge($params, [
            'app_id' => $this->appKey,
            'timestamp' => time(),
            'version' => '1.0',
        ]);

        $params['sig'] = $this->generate_signature($method, $params);

        $http = $this->getHttp();

        $response = $http->post($this->url . $method, $params);

        if ($response) {
            $result = json_decode(strval($response->getBody()), true);
        }

        // $this->checkErrorAndThrow($result);

        return $result;
    }


    public function request_get(string $method, array $params)
    {
        $params = array_merge($params, [
            'app_id' => $this->appKey,
            'timestamp' => time(),
        ]);

        $sig = $this->generate_signature($method, $params);

        $http = $this->getHttp();

        $url = $this->url.$method."?sig=".$sig."&".$this->concatParams($params);

        $response = $http->get($url, $params);
        if (!$response) {
            \Log::info('美团返回结果为null', [
                'method' => $method,
                'params' => $params,
                'response' => $response,
            ]);
            $result = [];
        } else {
            $result = json_decode(strval($response->getBody()), true);
        }

        // $this->checkErrorAndThrow($result);

        return $result;
    }

    public function request_get_html(string $method, array $params)
    {
        $params = array_merge($params, [
            'app_id' => $this->appKey,
            'timestamp' => time(),
        ]);

        $sig = $this->generate_signature($method, $params);

        $http = $this->getHttp();

        $url = $this->url.$method."?sig=".$sig."&".$this->concatParams($params);

        $response = $http->get($url, $params);
        $result = (string) $response->getBody();
        // \Log::info($result);

        // return $http->get($url, $params)->getBody();
        return $result;
    }

    public function concatParams($params) {
        ksort($params);
        $pairs = array();
        foreach($params as $key=>$val) {
            array_push($pairs, $key . '=' . $val);
        }
        return join('&', $pairs);
    }

    public function generate_signature($action, $params) {
        $params = $this->concatParams($params);
        $str = $this->url.$action.'?'.$params.$this->secret;
        return md5($str);
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


    public function checkErrorAndThrow($result)
    {
        // if (!$result || $result['code'] != 0) {
//            throw new MeituanDispatchException($result['message'], $result['code']);
//         }
    }
}
