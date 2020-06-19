<?php

return [
    "meituan" => [
        'app_key' => env('MEITUAN_APP_KEY', ''),
        'secret' => env('MEITUAN_SECRET', ''),
        'url' => 'https://peisongopen.meituan.com/api/'
    ],
    "fengniao" => [
        'app_key' => env('FENGNIAO_APP_KEY', ''),
        'secret' => env('FENGNIAO_SECRET', ''),
        'url' => 'https://exam-anubis.ele.me/anubis-webapi/'
    ],
    "shansong" => [
        'shop_id' => env('SS_SHOP_ID', ''),
        'client_id' => env('SS_CLIENT_ID', ''),
        'secret' => env('SS_SECRET', ''),
        // 'url' => 'http://open.ishansong.com'
        'url' => 'http://open.s.bingex.com'
    ],
    "yaojite" => [
        'app_key' => env('YAOJITE_APP_KEY', ''),
        'secret' => env('YAOJITE_SECRET', ''),
        'url' => 'https://waimaiopen.meituan.com/api/'
    ],
    "mrx" => [
        'app_key' => env('MRX_APP_KEY', ''),
        'secret' => env('MRX_SECRET', ''),
        'url' => 'https://waimaiopen.meituan.com/api/'
    ],
    "jay" => [
        'app_key' => env('JAY_APP_KEY', ''),
        'secret' => env('JAY_SECRET', ''),
        'url' => 'https://waimaiopen.meituan.com/api/'
    ],
    'jieri' => [
        '2020-02-16',
        '2020-02-17',
    ],
];