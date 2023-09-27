<?php

return [
    // 百度KEY
    "baidu" => [
        'client_id' => env('BAIDU_AI_ID', ""),
        'client_secret' => env('BAIDU_AI_SECRET', ""),
    ],
    // 高德KEY
    "amap" => [
        'AMAP_APP_KEY1' => env('AMAP_KEY1', ""),
    ],
    // 蜂鸟配送
    "ele" => [
        "app_key" => env("ELE_APP_KEY", ""),
        "secret" => env("ELE_SECRET", ""),
        "url" => env("ELE_URL", ""),
    ],
    // 达达配送
    "dada" => [
        "app_key" => env("DADA_APP_KEY", ""),
        "app_secret" => env("DADA_APP_SECRET", ""),
        "url" => env("DADA_APP_URL", ""),
        "source_id" => '118473',
    ],
    // 美全达配送
    "meiquanda" => [
        "app_id" => env("MEIQUANDA_APP_ID", ""),
        "app_secret" => env("MEIQUANDA_APP_SECRET", ""),
        "url" => env("MEIQUANDA_APP_URL", ""),
    ],
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
        "url" => "https://open.ishansong.com"
        // "url" => "http://open.s.bingex.com"
    ],
    // 闪送
    "shansongservice" => [
        "client_id" => env("SS_S_CLIENT_ID", ""),
        "secret" => env("SS_S_SECRET", ""),
        "url" => "https://open.ishansong.com"
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
    // 美团外卖-民康
    "jilin" => [
        "app_key" => '5616',
        "secret" => 'c514c7a4f9564c77b04004449d8a5784',
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
        "url" => "https://openic.sf-express.com"
    ],
    // 顺丰
    "shunfengservice" => [
        "app_id" => env("SHUNFENG_SERVER_APP_ID", ""),
        "app_key" => env("SHUNFENG_SERVER_APP_KEY", ""),
        "url" => "https://openic.sf-express.com"
    ],
    // Uu跑腿
    "uu" => [
        "app_id" => env("UU_APPID", ""),
        "app_key" => env("UU_APPKEY", ""),
        "url" => env("UU_URL", ""),
    ],
    // 桃子
    "taozi" => [
        "access_key" => env("TAOZI_ACCESS_KEY", ""),
        "secret_key" => env("TAOZI_SECRET_KEY", ""),
        "url" => env("TAOZI_URL", ""),
    ],
    "taozi_xia" => [
        "access_key" => env("TAOZI_XIA_ACCESS_KEY", ""),
        "secret_key" => env("TAOZI_XIA_SECRET_KEY", ""),
        "url" => env("TAOZI_XIA_URL", ""),
    ],
    // 外卖平台
    "takeout_map" => [
        1 => '美团外卖',2 => '饿了么',3 => '京东到家',
    ],
    // 跑腿运力平台
    "delivery_map" => [
        0 => "", 1 => '美团跑腿',2 => '蜂鸟配送',3 => '闪送',4 => '美全达',5 => '达达',6 => 'UU',7 => '顺丰',8 => '美团众包', 200 => '自配送', 210 => '平台配送', 220 => '未知配送'
    ],
    // 跑腿运力平台
    "meituan_bind_platform" => [
        4 => '美团闪购',25 => '美团外卖',31 => '美团闪购',
    ],
    // 外卖订单来源（应用）
    "meituan_develop_platform" => [
        0 => '手动', 1 => '药及特', 2 => '毛绒熊', 3 => '洁爱眼', 4 => '民康', 5 => '寝趣', 31 => '闪购', 35 =>'餐饮',
        121 => '饿了么', 135 => '饿了么餐饮'
    ],
    "order_ttl" => 480,
    "order_delay_ttl" => 60,
    "shop_setting" => [
        // 呼叫模式，1 自动，2 手动
        'call' => 1,
        // 延时发送订单，单位：秒
        'delay_send' => 60,
        // 检查订单重新发送，单位：分钟
        'delay_reset' => 8,
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
        'dd' => 1,
        'uu' => 1,
        'meiquanda' => 1,
        'zhongbao' => 1,
        // 仓库
        'warehouse' => 0,
        'warehouse_time' => '00:00-00:00',
    ],
    // 门店分类
    "shop_category_map" => [
        '200001' => '综合药店',
        '200902' => '成人用品',
        '200903' => '眼镜店',
        '180001' => '浪漫鲜花',
        '110001' => '小吃美食',
        '110007' => '正餐快餐',
        '110006' => '海鲜烧烤',
        '270001' => '烘焙蛋糕',
        '110003' => '香锅烤鱼',
        '110004' => '西餐料理',
        '110005' => '日韩料理',
        '270003' => '奶茶果汁',
        '120001' => '超市百货',
        '120002' => '水站奶站',
        '120007' => '酒水茶行',
        '150001' => '生鲜果蔬',
        '150002' => '冷冻速食',
        '240001' => '孕婴用品',
        '210001' => '其它'
    ],
    "shop_category_list" => [
        [
            'id' => 200001,
            'name' => '综合药店',
        ],
        [
            'id' => 200902,
            'name' => '成人用品',
        ],
        [
            'id' => 200903,
            'name' => '眼镜店',
        ],
        [
            'id' => 180001,
            'name' => '浪漫鲜花',
        ],
        [
            'id' => 110001,
            'name' => '小吃美食',
        ],
        [
            'id' => 110007,
            'name' => '正餐快餐',
        ],
        [
            'id' => 110006,
            'name' => '海鲜烧烤',
        ],
        [
            'id' => 270001,
            'name' => '烘焙蛋糕',
        ],
        [
            'id' => 110003,
            'name' => '香锅烤鱼',
        ],
        [
            'id' => 110004,
            'name' => '西餐料理',
        ],
        [
            'id' => 110005,
            'name' => '日韩料理',
        ],
        [
            'id' => 270003,
            'name' => '奶茶果汁',
        ],
        [
            'id' => 120001,
            'name' => '超市百货',
        ],
        [
            'id' => 120002,
            'name' => '水站奶站',
        ],
        [
            'id' => 120007,
            'name' => '酒水茶行',
        ],
        [
            'id' => 150001,
            'name' => '生鲜果蔬',
        ],
        [
            'id' => 150002,
            'name' => '冷冻速食',
        ],
        [
            'id' => 240001,
            'name' => '孕婴用品',
        ],
        [
            'id' => 210001,
            'name' => '其它',
        ]
    ],
    "stock_urls" => [
        1 => 'http://psapi1.meiquanjiankang.com/api/stock',
        2 => 'http://psapi2.meiquanjiankang.com/api/stock',
    ],
    'delivery_order_status' => [
        0 => '新订单',
        3 => '[预约]待呼叫',
        5 => '余额不足',
        7 => '取消呼叫',
        8 => '即将呼叫',
        10 => '暂无运力',
        20 => '待接单',
        30 => '待接单',
        40 => '待取货',
        50 => '待取货',
        60 => '配送中',
        70 => '已完成',
        75 => '已完成',
        80 => '异常',
        99 => '已取消',
    ],

];
