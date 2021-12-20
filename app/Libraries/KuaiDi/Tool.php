<?php

namespace App\Libraries\KuaiDi;

class Tool
{
    /**
     * 获取签名
     * @param $para array 加密的参数数组
     * @param $encKey string 加密的key
     * @return bool|string 生产的签名 sign
     */
    public static function getSign($para, $key, $customer)
    {
        return strtoupper(md5(json_encode($para, JSON_UNESCAPED_UNICODE) . $key . $customer));
    }
    /**
     * 获取签名
     * @param $para array 加密的参数数组
     * @param $encKey string 加密的key
     * @return bool|string 生产的签名 sign
     */
    public static function getOrderSign($para, $t, $key, $secret)
    {
        return strtoupper(md5(json_encode($para, JSON_UNESCAPED_UNICODE) . $t . $key . $secret));
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
     * @param $para array 签名参数组
     * @return array 去掉空值与签名参数后的新签名参数组
     * 除去数组中的空值和签名参数
     */
    private static function paraFilter($para)
    {
        $paraFilter = [];
        foreach ($para as $key => $val) {
            if (in_array($key, ["sign", "sign_type", "key"]) || (empty($val) && !is_numeric($val))) { // "",null
                continue;
            } else {
                $paraFilter[$key] = $para[$key];
            }
        }
        return $paraFilter;
    }

    /**
     * @param $para array 排序前的数组
     * @return mixed 排序后的数组
     * 对数组排序
     */
    private static function argSort($para)
    {
        ksort($para);
        // reset($para);
        return $para;
    }

    /**
     * @param $para array 需要拼接的数组
     * @return string 拼接完成以后的字符串
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     */
    private static function createLinkstring($para)
    {
        $arg = "";
        foreach ($para as $key => $val) {
            if ($val !== "") {
                $arg .= $key . '=' . $val . '&';
            }
        }
        return substr($arg, 0, -1);
    }

    /**
     * @param $prestr string 需要签名的字符串
     * @param $key string 私钥
     * @return string 签名结果
     * 生成签名
     */
    private static function md5Verify($prestr, $key)
    {
        // \Log::info($prestr);
        // \Log::info(strtoupper($prestr . '&key=' .  $key));
        // \Log::info(md5(strtoupper($prestr . '&key=' .  $key)));
        // \Log::info(strtoupper(md5(strtoupper($prestr . '&key=' .  $key))));
        return strtoupper(md5(strtoupper($prestr . '&key=' . $key)));
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
