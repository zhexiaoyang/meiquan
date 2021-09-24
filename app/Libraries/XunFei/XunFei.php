<?php

namespace App\Libraries\XunFei;

class XunFei
{
    function xfyun($file){
        // 印刷文字识别 webapi 接口地址
        $api = "http://webapi.xfyun.cn/v1/service/v1/ocr/general";
        // 应用ID (必须为webapi类型应用，并印刷文字识别服务，参考帖子如何创建一个webapi应用：http://bbs.xfyun.cn/forum.php?mod=viewthread&tid=36481)
        $XAppid = "dc248085";
        // 接口密钥(webapi类型应用开通印刷文字识别服务后，控制台--我的应用---印刷文字识别---服务的apikey)
        $Apikey = "bf2c0d025b42163c61876cbfb413c6d8";
        $XCurTime =time();
        $XParam ="";
        $XCheckSum ="";
        // 语言类型和文本位置信息(默认不返回)
        $Param= array(
            "language"=>"cn|en",
            "location"=>"false",
        );
        // 上传图片文件地址并base64位编码
        $image=file_get_contents($file);
        $image=base64_encode($image);

        $Post = array(
            'image' => $image,
        );

        $XParam = base64_encode(json_encode($Param));
        $XCheckSum = md5($Apikey.$XCurTime.$XParam);
        // 组装http请求头
        $headers = array();
        $headers[] = 'X-CurTime:'.$XCurTime;
        $headers[] = 'X-Param:'.$XParam;
        $headers[] = 'X-Appid:'.$XAppid;
        $headers[] = 'X-CheckSum:'.$XCheckSum;
        $headers[] = 'Content-Type:application/x-www-form-urlencoded; charset=utf-8';
        $res = $this->http_request($api, $Post, $headers);
        $res_data = json_decode($res, true);

        // 获取地址信息
        $str = '';
        $name = '';
        $phone = '';
        $phone_tmp = '';
        $phone_data = [];
        $address_all = '';
        $address = '';
        $address_detail = '';
        \Log::info("000", $res_data);
        if (!empty($res_data['data']['block'][0]['line'])) {
            foreach ($res_data['data']['block'][0]['line'] as $k => $v) {
                $str .= $v['word'][0]['content'];
                if (strstr( $v['word'][0]['content'], "***")) {
                    continue;
                }
                if (strstr( $v['word'][0]['content'], "手机尾号")) {
                    continue;
                }
                if ($name === '') {
                    $name = $v['word'][0]['content'];
                    continue;
                }
                if (strstr($v['word'][0]['content'], "虚拟号码")) {
                    preg_match_all('/\d+/', $v['word'][0]['content'],$phone_data);
                    if (!empty($phone_data[0])) {
                        $phone = $phone_data[0][0];
                        $phone_tmp = $phone_data[0][1];
                    }
                    break;
                }
                if ($phone == '') {
                    $address_all .= $v['word'][0]['content'];
                }
            }
        }

        \Log::info($str);

        if ($address_all) {
            $num = mb_strrpos($address_all,"（");
            $address = mb_substr($address_all, 0, $num);
            $address_detail = mb_substr($address_all, $num -1, -1);
        }

        $data = compact("name", "phone", "phone_tmp", "address", "address_detail");

        \Log::info('讯飞文字-地址判断结果', $data);
        return $data;
    }

    /**
     * 发送post请求
     * @param string $url 请求地址
     * @param array $post_data post键值对数据
     * @return string
     */
    function http_request($url, $post_data, $headers) {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => $headers,
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        \Log::info("讯飞文字识别返回", json_decode($result, true) ?: []);

        return $result;
        // 错误码链接：https://www.xfyun.cn/document/error-code (code返回错误码时必看)
        // return "success";
    }
}
