<?php

namespace App\Handlers;

class AddressRecognitionHandler
{
    public function run($text) {
        $curl = curl_init();
        $postData = array(
            'text' => $text,
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://aip.baidubce.com/rpc/2.0/nlp/v1/address?access_token={$this->getAccessToken()}",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode($postData, JSON_UNESCAPED_UNICODE)
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        \Log::info("百度地址识别返回：" . $response);
        return $response;
    }

    /**
     * 使用 AK，SK 生成鉴权签名（Access Token）
     * @return string 鉴权签名信息（Access Token）
     */
    private function getAccessToken(){
        $curl = curl_init();
        $postData = array(
            'grant_type' => 'client_credentials',
            'client_id' => config('ps.baidu.client_id'),
            'client_secret' => config('ps.baidu.client_secret'),
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://aip.baidubce.com/oauth/2.0/token',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query($postData)
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $rtn = json_decode($response);
        return $rtn->access_token;
    }
}
