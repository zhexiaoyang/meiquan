<?php

namespace App\Libraries\Baidu;

class Baidu
{
    public function shibie($image)
    {
        $data = [
            'access_token' => $this->token(),
            // 'image' => $image,
            'url' => 'https://image.meiquanda.com/000.png',
            'language_type' => 'CHN_ENG',
            // 'detect_language' => true,
            // 'detect_direction' => true,
            // 'paragraph' => true
        ];

        $res = $this->post('https://aip.baidubce.com/rest/2.0/ocr/v1/accurate_basic', $data);

        $data = json_decode($res, true);

        $str = '';

        if (!empty($data['words_result'])) {
            foreach ($data['words_result'] as $v) {
                if ($v['words']) {
                    $str .= $v['words'];
                }
            }
        }

        \Log::info($str);

        return $str;
    }

    public function token()
    {
        // return '24.bdd85a25ccba8bd8c04c62ffe88f392e.2592000.1634752930.282335-24838198';
        $id = 'NyxUWDudKDTu1Kc5phxR7y3N';
        $secret = 'ygefdFXIGl9IDTz7jvm4AqZqIgukNvuM';
        $res = file_get_contents("https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id={$id}&client_secret={$secret}");
        // \Log::info("token", [$res]);
        $data = json_decode($res, true);
        return $data['access_token'];
    }

    public function post($url, $data)
    {
        $ch = curl_init();
        $header = ['Content-Type:application/x-www-form-urlencoded']; //设置一个你的浏览器agent的header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        // \Log::info("data", $data);
        \Log::info("token", [$output]);
        return $output;
    }
}
