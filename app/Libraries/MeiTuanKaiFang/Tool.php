<?php

namespace App\Libraries\MeiTuanKaiFang;

use Illuminate\Support\Facades\Cache;

class Tool
{
    /**
     * 获取签名
     */
    public static function get_sign($data, $sign_key)
    {
        if ($data == null) {
            return null;
        }
        ksort($data);
        $result_str = "";
        foreach ($data as $key => $val) {
            if ( $key != null && $key != "" && $key != "sign" ) {
                $result_str = $result_str . $key . $val;
            }
        }
        $result_str = $sign_key . $result_str;


        $ret = bin2hex(sha1($result_str, true));

        return $ret;
    }

    public static function concat_params($params) {
        ksort($params);
        $pairs = array();
        foreach($params as $key=>$val) {
            array_push($pairs, $key . '=' . $val);
        }
        return join('&', $pairs);
    }

    public static function binding($shop_id)
    {
        // 106791
        // lq1gtktmr3ofrjny
        // 106792
        // 36cvt5p8joq0jiiw
        $params = [
            'developerId' => config('ps.meituan_open.app_id'),
            'businessId' => 16,
            'timestamp' => time(),
            'ePoiId' => $shop_id,
        ];
        $params['sign'] = self::get_sign($params, config('ps.meituan_open.app_key'));

        return 'https://open-erp.meituan.com/storemap?' . Tool::concat_params($params);
    }

    public static function releasebinding($shop_id)
    {
        // 106791
        // lq1gtktmr3ofrjny
        // 106792
        // 36cvt5p8joq0jiiw
        $key = 'meituan:open:token:' . $shop_id;
        $params = [
            // 'appAuthToken' => Cache::get($key),
            'developerId' => config('ps.meituan_open.app_id'),
            'businessId' => 16,
            'timestamp' => time(),
            'ePoiId' => $shop_id,
            'charset' => 'UTF-8'
        ];
        $params['sign'] = self::get_sign($params, config('ps.meituan_open.app_key'));

        return 'https://open-erp.meituan.com/general/unauth?' . Tool::concat_params($params);
    }
}
