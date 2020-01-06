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
    "meiquan" => [
        'app_key' => env('MEIQUAN_APP_KEY', ''),
        'secret' => env('MEIQUAN_SECRET', ''),
        'url' => 'https://waimaiopen.meituan.com/api/'
    ],
];