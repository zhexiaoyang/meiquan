<?php

return [
    'alipay' => [
        // 支付宝分配的 APPID
        'app_id' => env('ALI_APP_ID', ''),

        // 支付宝异步通知地址-中台
        'notify_url' => env('ALI_NOTIFY_URL', ''),
        // 支付宝异步通知地址-中台
        'app_notify_url' => env('ALI_APP_NOTIFY_URL', ''),

        // 支付成功后同步通知地址
        'return_url' => '',

        // 阿里公共密钥，验证签名时使用
        'ali_public_key' => env('ALI_PUBLIC_KEY', ''),

        // 自己的私钥，签名时使用
        'private_key' => env('ALI_PRIVATE_KEY', ''),

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/alipay.log'),
            //  'level' => 'debug'
            //  'type' => 'single', // optional, 可选 daily.
            //  'max_file' => 30,
        ],

        // optional，设置此参数，将进入沙箱模式
        // 'mode' => 'dev',
    ],
    'mqjk_alipay' => [
        // 支付宝分配的 APPID
        'app_id' => env('MQJK_ALI_APP_ID', ''),

        // 支付宝异步通知地址-中台
        'notify_url' => env('MQJK_ALI_NOTIFY_URL', ''),
        // 支付宝异步通知地址-中台
        'app_notify_url' => env('MQJK_ALI_APP_NOTIFY_URL', ''),

        // 支付成功后同步通知地址
        'return_url' => '',

        // 阿里公共密钥，验证签名时使用
        'ali_public_key' => env('MQJK_ALI_PUBLIC_KEY', ''),

        // 自己的私钥，签名时使用
        'private_key' => env('MQJK_ALI_PRIVATE_KEY', ''),

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/alipay-mqjk.log'),
            //  'level' => 'debug'
            //  'type' => 'single', // optional, 可选 daily.
            //  'max_file' => 30,
        ],

        // optional，设置此参数，将进入沙箱模式
        // 'mode' => 'dev',
    ],

    'wechat' => [
        // 公众号 APPID
        'app_id' => env('WECHAT_APP_ID', ''),

        // 小程序 APPID
        'miniapp_id' => env('WECHAT_MINIAPP_ID', ''),
        'miniapp_app_secret' => env('WECHAT_MINIAPP_APP_SECRET', ''),

        // APP 引用的 appid
        'appid' => env('WECHAT_APPID', ''),

        // 微信支付分配的微信商户号
        'mch_id' => env('WECHAT_MCH_ID', ''),

        // 微信支付异步通知地址
        'notify_url' => env('WECHAT_NOTIFY_URL', ''),

        // 微信支付签名秘钥
        'key' => env('WECHAT_KEY', ''),

        // 客户端证书路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_client' => '',

        // 客户端秘钥路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_key' => '',

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/wechat.log'),
            //  'level' => 'debug'
            //  'type' => 'single', // optional, 可选 daily.
            //  'max_file' => 30,
        ],

        // optional
        // 'dev' 时为沙箱模式
        // 'hk' 时为东南亚节点
        // 'mode' => 'dev',
    ],

    'wechat_supplier' => [
        // 公众号 APPID
        'app_id' => env('WECHAT_APP_ID', ''),

        // 小程序 APPID
        'miniapp_id' => env('SUPPLIER_WECHAT_MINIAPP_ID', ''),

        // APP 引用的 appid
        'appid' => env('WECHAT_APPID', ''),

        // 微信支付分配的微信商户号
        'mch_id' => env('SUPPLIER_WECHAT_MCH_ID', ''),

        // 微信支付异步通知地址
        'notify_url' => env('WECHAT_SUPPLIER_NOTIFY_URL', ''),

        // 微信支付签名秘钥
        'key' => env('SUPPLIER_WECHAT_KEY', ''),

        // 客户端证书路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_client' => config_path('cert/apiclient_cert.pem'),

        // 客户端秘钥路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_key' => config_path('cert/apiclient_key.pem'),

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/wechat_supplier.log'),
        ],
    ],

    'wechat_supplier_money' => [
        // 公众号 APPID
        'app_id' => env('WECHAT_APP_ID', ''),

        // 小程序 APPID
        'miniapp_id' => env('OPERATE_WECHAT_MINIAPP_ID', ''),

        // APP 引用的 appid
        'appid' => env('WECHAT_APPID', ''),

        // 微信支付分配的微信商户号
        'mch_id' => env('SUPPLIER_WECHAT_MCH_ID', ''),

        // 微信支付异步通知地址
        'notify_url' => 'http://psapi.meiquanda.com/api/payment/wechat/notify2',

        // 微信支付签名秘钥
        'key' => env('SUPPLIER_WECHAT_KEY', ''),

        // 客户端证书路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_client' => '',

        // 客户端秘钥路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_key' => '',

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/wechat_supplier.log'),
        ],
    ],

    'wechat_operate_money' => [
        // 公众号 APPID
        'app_id' => env('OPERATE_WECHAT_APP_ID', ''),

        // 小程序 APPID
        'miniapp_id' => env('OPERATE_WECHAT_MINIAPP_ID', ''),
        'miniapp_app_secret' => env('OPERATE_WECHAT_MINIAPP_APP_SECRET', ''),

        // APP 引用的 appid
        'appid' => env('WECHAT_APPID', ''),

        // 微信支付分配的微信商户号
        'mch_id' => env('OPERATE_WECHAT_MCH_ID', ''),

        // 微信支付异步通知地址
        'notify_url' => 'http://psapi.meiquanda.com/api/payment/wechat/notify/operate',

        // 微信支付签名秘钥
        'key' => env('OPERATE_WECHAT_KEY', ''),

        // 客户端证书路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_client' => '',

        // 客户端秘钥路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_key' => '',

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/wechat_operate.log'),
        ],
    ],
];
