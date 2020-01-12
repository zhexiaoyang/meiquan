<?php

return [
    "meituan" => [
        'app_key' => env('MEITUAN_APP_KEY', ''),
        'secret' => env('MEITUAN_SECRET', ''),
        'url' => 'https://peisongopen.meituan.com/api/'
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
];