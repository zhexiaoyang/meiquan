<?php

namespace App\Libraries\TaoZi;

class Tool
{
    /**
     * @param array $params
     * @param string $secret_key
     * @return string
     * @author zhangzhen
     * @data 2021/11/2 3:58 下午
     */
    public static function getSign(array $params, string $secret_key)
    {
        return md5($params['accessKey'] . $params['timestamp'] . $secret_key);
    }

    public static function getSign2(array $params, string $secret_key)
    {
        return md5($secret_key . 'orgID' . $params['thirdOrgID'] . 'timestamp' .  $params['timestamp']);
    }
}
