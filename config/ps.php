<?php

return [
    // 美团配送
    "meituan" => [
        "app_key" => env("MEITUAN_APP_KEY", ""),
        "secret" => env("MEITUAN_SECRET", ""),
        "url" => "https://peisongopen.meituan.com/api/"
    ],
    // 蜂鸟配送
    "fengniao" => [
        "app_key" => env("FENGNIAO_APP_KEY", ""),
        "secret" => env("FENGNIAO_SECRET", ""),
        "url" => "https://open-anubis.ele.me/anubis-webapi/"
    ],
    // 闪送
    "shansong" => [
        "shop_id" => env("SS_SHOP_ID", ""),
        "client_id" => env("SS_CLIENT_ID", ""),
        "secret" => env("SS_SECRET", ""),
        "url" => "http://open.ishansong.com"
        // "url" => "http://open.s.bingex.com"
    ],
    // 美团外卖-药及特
    "yaojite" => [
        "app_key" => env("YAOJITE_APP_KEY", ""),
        "secret" => env("YAOJITE_SECRET", ""),
        "url" => "https://waimaiopen.meituan.com/api/"
    ],
    // 美团外卖-毛绒熊
    "mrx" => [
        "app_key" => env("MRX_APP_KEY", ""),
        "secret" => env("MRX_SECRET", ""),
        "url" => "https://waimaiopen.meituan.com/api/"
    ],
    // 美团外卖-洁爱眼
    "jay" => [
        "app_key" => env("JAY_APP_KEY", ""),
        "secret" => env("JAY_SECRET", ""),
        "url" => "https://waimaiopen.meituan.com/api/"
    ],
    // 美团外卖-民康
    "minkang" => [
        "app_key" => env("MINKANG_APP_KEY", ""),
        "secret" => env("MINKANG_SECRET", ""),
        "url" => "https://waimaiopen.meituan.com/api/"
    ],
    // 美团外卖-寝趣
    "qinqu" => [
        "app_key" => env("QINQU_APP_KEY", ""),
        "secret" => env("QINQU_SECRET", ""),
        "url" => "https://waimaiopen.meituan.com/api/"
    ],
    // 美团外卖-美全服务商
    "meiquan" => [
        "app_key" => env("MEIQUAN_APP_KEY", ""),
        "secret" => env("MEIQUAN_SECRET", ""),
        "url" => "https://waimaiopen.meituan.com/api/"
    ],
    // 药柜
    "yaogui" => [
        "app_key" => env("YAOGUI_APP_KEY", ""),
        "secret" => env("YAOGUI_SECRET", ""),
        "url" => "https://openapi.vendingtech.vip/openapi/v1/"
        // "url" => "https://openapi-test.vendingtech.vip/openapi/v1/"
    ],
    // 顺丰
    "shunfeng" => [
        "app_id" => env("SHUNFENG_APP_ID", ""),
        "app_key" => env("SHUNFENG_APP_KEY", ""),
        "url" => "https://commit-openic.sf-express.com"
    ],
    "jieri" => [
        "2020-02-16",
        "2020-02-17",
    ],
    "order_ttl" => 900,
    "order_delay_ttl" => 60,
    "shop_setting" => [
        // 延时发送订单，单位：秒
        'delay_send' => 60,
        // 检查订单重新发送，单位：分钟
        'delay_reset' => 15,
        // 重新发送是是否保持之前订单呼叫，1 是、2 否
        'type' => 1,
        // 交通工具（0 未指定，8 汽车）
        'tool' => 0,
        // 平台
        'meituan' => 1,
        'fengniao' => 1,
        'shansong' => 1,
        'shunfeng' => 1,
        'dada' => 1,
    ]

];
