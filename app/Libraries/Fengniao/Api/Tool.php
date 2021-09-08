<?php

namespace App\Libraries\Fengniao\Api;

class Tool
{
    /**
     * 获取签名
     * @param $para array 加密的参数数组
     * @param $encKey string 加密的key
     * @return bool|string 生产的签名 sign
     */
    public static function getSign($para, $encKey)
    {
        if (empty($para) || empty($encKey)) {
            return false;
        }
        // $para['secret'] = $encKey;
        $para = self::argSort($para);
        $str = self::createLinkstring($para);
        $sign = self::md5Verify($str, $encKey);
        return $sign;
    }

    /**
     * @param $param  array 参数数组
     * @param $encKey string  加密key
     * @param $sign string 签名
     * @return bool 正确 true 错误 false
     * 判断签名是否正确
     */
    public static function isSignCorrect($param, $encKey, $sign)
    {
        if (empty($sign)) {
            return false;
        } else {
            $prestr = self::getSign($param, $encKey);
            return $prestr === $sign ? true : false;
        }
    }

    /**
     * @param $para array 排序前的数组
     * @return mixed 排序后的数组
     * 对数组排序
     */
    public static function argSort($para)
    {
        ksort($para);
        return $para;
    }

    /**
     * @param $para array 需要拼接的数组
     * @return string 拼接完成以后的字符串
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     */
    public static function createLinkstring($para)
    {
        $data = [];
        foreach ($para as $key => $val) {
            array_push($data, $key . '=' .$val);
        }
        return implode("&", $data);
    }

    /**
     * 生成签名
     */
    private static function md5Verify($prestr, $encKey)
    {
        $str = $encKey . $prestr;

        \Log::info($str);

        $re = hash('sha256', $str);

        \Log::info($re);

        return $re;

        $res = bin2hex($re);

        \Log::info($res);

        return $res;
    }

    public static function ticket()
    {
        //字符组合
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randstr = '';

        for ($i = 0; $i < 8; $i++) {
            $num=mt_rand(0,35);
            $randstr .= $str[$num];
        }
        $randstr .= '-';

        for ($i = 0; $i < 4; $i++) {
            $num=mt_rand(0,35);
            $randstr .= $str[$num];
        }
        $randstr .= '-';

        for ($i = 0; $i < 4; $i++) {
            $num=mt_rand(0,35);
            $randstr .= $str[$num];
        }
        $randstr .= '-';

        for ($i = 0; $i < 4; $i++) {
            $num=mt_rand(0,35);
            $randstr .= $str[$num];
        }
        $randstr .= '-';

        for ($i = 0; $i < 12; $i++) {
            $num=mt_rand(0,35);
            $randstr .= $str[$num];
        }

        return $randstr;
    }
}
