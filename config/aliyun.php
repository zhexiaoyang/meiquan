<?php

/*
 * 阿里云OSS配置
 */

return [
    'access_key_id' => env('ACCESS_KEY_ID'),
    'access_key_secret' => env('ACCESS_KEY_SECRET'),
    // Bucket所在地域对应的Endpoint
    'bucket' => 'peisong-auth',
    'endpoint' => 'https://oss-cn-beijing.aliyuncs.com',
    'rp_dir' => 'picture_rp/',


];
