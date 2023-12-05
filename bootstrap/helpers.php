<?php

function amap_address_search ($key, $city, $lng, $lat) {
    $url = "https://restapi.amap.com/v3/assistant/inputtips?key=".config('ps.amap.AMAP_APP_KEY1')."&keywords={$key}&type=&location={$lng},{$lat}&city={$city}&datatype=all";
    $str = file_get_contents($url);
    $data = json_decode($str, true);
    return $data['tips'] ?? [];
}

// 将米转换成公里文字
function get_kilometre ($metre): string
{
    if ($metre < 1000) {
        return $metre . '米';
    }
    return sprintf("%.2f 公里", $metre / 1000);
}

// 获取两个点的距离（1 距离，2 步行，3 骑行）
function get_distance_title($lng1, $lat1, $lng2, $lat2): string
{
    $distance = 0;
    $r = rand(1, 3);
    $r = 1;
    if ($r == 1) {
        $url = "https://restapi.amap.com/v3/distance?origins={$lng1},{$lat1}&destination={$lng2},{$lat2}&key=".config('ps.amap.AMAP_APP_KEY1')."&type=1";
        $str = file_get_contents($url);
        $data = json_decode($str, true);
        $distance = $data['results'][0]['distance'] ?? 0;
    } else if ($r == 2) {
        $url = "https://restapi.amap.com/v3/direction/walking?origin={$lng1},{$lat1}&destination={$lng2},{$lat2}&key=".config('ps.amap.AMAP_APP_KEY1');
        $str = file_get_contents($url);
        $data = json_decode($str, true);
        $distance = $data['route']['paths'][0]['distance'] ?? 0;
    } else if ($r == 3) {
        $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$lng1},{$lat1}&destination={$lng2},{$lat2}&key=".config('ps.amap.AMAP_APP_KEY1');
        $str = file_get_contents($url);
        $data = json_decode($str, true);
        $distance = $data['data']['paths'][0]['distance'] ?? 0;
    }
    if ($distance > 1000) {
        $res_text = sprintf("%.1f 公里", $distance / 1000);
    } else {
        $res_text = $distance . ' 米';
    }
    return $res_text;
}

// 订单详情中，预约订单，08-07 08:10前送达|立即送达，08-07 08:10下单
function tranTime4($estimate_arrival_time): string
{
    if (time() < $estimate_arrival_time) {
        return '，剩余 ' . intval( ($estimate_arrival_time - time()) / 60 ) . '分钟';
    } else {
        return '，已超时 ' . intval( (time() - $estimate_arrival_time) / 60 ) . '分钟';
    }
}

// 派送中的订单标题，今日 08:32 前送达，超时 34分钟，后半部分
function tranTime3($estimate_arrival_time): string
{
    if (time() < $estimate_arrival_time) {
        return '，剩余 ' . intval( ($estimate_arrival_time - time()) / 60 ) . '分钟';
    } else {
        return '，已超时 ' . intval( (time() - $estimate_arrival_time) / 60 ) . '分钟';
    }
}

// 派送中的订单标题，今日 08:32 前送达，超时 34分钟，前半部分
function tranTime2($time) {
    $rtime = date("m-d H:i",$time);
    $htime = date("H:i", $time);

    // $time = time() - $time;

    if ($time > strtotime(date("Y-m-d", time())) && $time < strtotime(date("Y-m-d", strtotime('+1 day'))))
    {
        if(date('Ymd', $time) == date('Ymd'))
            $str = '今日 '.$htime;
        else
            $str = '明日 '.$htime;
    }
    else
    {
        $str = $rtime;
    }
    return $str;
}

// 正常
function tranTime($time) {
    $rtime = date("m-d H:i",$time);
    $htime = date("H:i",$time);

    $time = time() - $time;

    if ($time < 60)
    {
        $str = '刚刚';
    }
    elseif ($time < 60 * 60)
    {
        $min = floor($time/60);
        $str = $min.'分钟前';
    }
    elseif ($time < 60 * 60 * 24)
    {
        $h = floor($time/(60*60));
        // $str = $h.'小时前 '.$htime;
        $str = $h.'小时前';
    }
    elseif ($time < 60 * 60 * 24 * 3)
    {
        $d = floor($time/(60*60*24));
        if($d==1)
            // $str = '昨天 '.$rtime;
            $str = '昨天';
        else
            // $str = '前天 '.$rtime;
            $str = '前天';
    }
    else
    {
        $str = $rtime;
    }
    return $str;
}

// 正常
function formetTime($time) {
    $str = '';
    $time = ($time + 86400) - time();
    if ($time < 60)
    {
        $str = $time . '秒';
    }
    elseif ($time < 60 * 60)
    {
        $min = floor($time/60);
        $str = $min.'分钟';
    }
    elseif ($time < 60 * 60 * 24)
    {
        $h = floor($time/(60*60));
        $str = $h.'小时';
    }
    return $str;
}

function randomPassword($length = 6)
{
    //字符组合
    // $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $randstr = '';

    for ($i = 0; $i < $length; $i++) {
        $num=mt_rand(0,35);
        $randstr .= $str[$num];
    }
    return $randstr;
}

//腾讯转百度坐标转换
function coordinate_switchf($a,$b){
    $x = (double)$b ;
    $y = (double)$a;
    $x_pi = 3.14159265358979324;
    $z = sqrt($x * $x+$y * $y) + 0.00002 * sin($y * $x_pi);
    $theta = atan2($y,$x) + 0.000003 * cos($x*$x_pi);
    $gb = number_format($z * cos($theta) + 0.0065,6);
    $ga = number_format($z * sin($theta) + 0.006,6);
    return ['longitude'=>$ga,'latitude'=>$gb];
}

//百度转腾讯坐标转换
function coordinate_switch($a,$b){
    $x = (double)$b - 0.0065;
    $y = (double)$a - 0.006;
    $x_pi = 3.14159265358979324;
    $z = sqrt($x * $x+$y * $y) - 0.00002 * sin($y * $x_pi);
    $theta = atan2($y,$x) - 0.000003 * cos($x*$x_pi);
    $gb = number_format($z * cos($theta),15);
    $ga = number_format($z * sin($theta),15);
    return ['longitude'=>$ga,'latitude'=>$gb];
}

/**
 * 通过门店ID获取达达自主注册source_id
 * @data 2022/5/27 1:16 下午
 */
function get_dada_source_by_shop($shop_id) {
    $key = 'dada_source_id:' . $shop_id;
    return \Illuminate\Support\Facades\Cache::remember($key, 0, function () use ($shop_id) {
        if ($shipper = \App\Models\ShopShipper::where('shop_id', $shop_id)->where('platform', 5)->first()) {
            return $shipper->source_id;
        }
    });
}

/**
 * 获取美团开放平台分类
 * @param $platform
 * @return string
 * @author zhangzhen
 * @data 2022/4/12 11:58 上午
 */
function get_meituan_develop_platform($platform) :string
{
    $platforms = config('ps.meituan_develop_platform');
    return $platforms[$platform] ?? '';
}

/**
 * @param $stime '20:00'
 * @param $etime '06:00'
 * @param string $current '12:00'
 * @return bool
 * @author zhangzhen
 * @data 2022/1/9 9:28 上午
 */
function in_time_status($stime, $etime, $current = '') {
    $current_time = time();
    if ($current) {
        $current_time = strtotime($current);
    }

    $s_time = strtotime($stime);
    $e_time = strtotime($etime);

    if ($e_time > $s_time) {
        if (($current_time >= $s_time) && ($current_time <= $e_time)) {
            return true;
        }
    }

    if ($e_time < $s_time) {
        if (($current_time <= $e_time) || ($current_time >= $s_time)) {
            return true;
        }
    }

    return false;
}

/**
 * 获取蜂鸟配送token
 * @return mixed
 */
function fengNiaoToken() {
    return \Illuminate\Support\Facades\Cache::remember('feng_niao_token', 43200, function () {
        $fengniao = app('fengniao');
        $data = $fengniao->generateSign();
        return $data['data']['access_token'];
    });
}

function rider_location ($lng,$lat) {

    $lng *= 1000000;
    $lat *= 1000000;
    $data['lng'] = ($lng + 10000) / 1000000;
    $data['lat'] = ($lat + 10000) / 1000000;

    return $data;
}

/**
 * 百度转高德
 */
function gd2bd($lng,$lat)
{
    // $url = "https://api.map.baidu.com/geoconv/v1/?coords={$lng},{$lat}&from=3&to=5&ak=fL3camAQGEm7or6773IUG0K2dmPdTEYb";
    // $arrContextOptions = [
    //     'ssl' => [
    //         'verify_peer' => false,
    //         'verify_peer_name' => false,
    //     ]
    // ];
    // $res = file_get_contents($url, false, stream_context_create($arrContextOptions));
    // $res = json_decode($res, true);
    // if (isset($res['result'][0]['x'])) {
    //     $data['lng'] = $res['result'][0]['x'];
    //     $data['lat'] = $res['result'][0]['y'];
    //     // \Log::info("百度地图坐标转换");
    // } else {
    //     $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
    //     $x = $lng;
    //     $y = $lat;
    //     $z = sqrt($x * $x +$y * $y) - 0.00002 * sin($y * $x_pi);
    //     $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
    //     $data['lng'] = $z * cos($theta) + 0.0065;
    //     $data['lat'] = $z * sin($theta) + 0.0065;
    // }
    $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
    $x = $lng;
    $y = $lat;
    $z = sqrt($x * $x +$y * $y) - 0.00002 * sin($y * $x_pi);
    $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
    $data['lng'] = $z * cos($theta) + 0.0065;
    $data['lat'] = $z * sin($theta) + 0.0065;

    return $data;
}

function bd2gd($lng,$lat)
{
    $url = "https://api.map.baidu.com/geoconv/v1/?coords={$lng},{$lat}&from=5&to=3&ak=fL3camAQGEm7or6773IUG0K2dmPdTEYb";
    $arrContextOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];
    // $res = file_get_contents($url);
    $res = file_get_contents($url, false, stream_context_create($arrContextOptions));
    $res = json_decode($res, true);
    if (isset($res['result'][0]['x'])) {
        $data['lng'] = $res['result'][0]['x'];
        $data['lat'] = $res['result'][0]['y'];
        // \Log::info("百度地图坐标转换");
    } else {
        $z = sqrt($lng * $lng + $lat * $lat) + 0.00002 * sin($lat * 52.35987755982988);
        $theta = atan2($lat, $lng) + 0.000003 * cos($lng * 52.35987755982988);
        $data['lng'] = $z * cos($theta) + 0.0065;
        $data['lat'] = $z * sin($theta) + 0.0065;
    }
    return $data;
}

/**
 * 时间加价
 * @return int
 */
function timeMoney() {
    $money = 0;

    // 夜间加价
    if (time() >= strtotime(date("Y-m-d 21:00:00")) || time() < strtotime(date("Y-m-d 6:00:00"))) {
        $money +=3;
    }

    // 午峰加价
    if (time() >= strtotime(date("Y-m-d 11:00:00")) && time() < strtotime(date("Y-m-d 13:00:00"))) {
        $money +=2;
    }

    return $money;
}

/**
 * （美全达）时间加价
 * @return int
 */
function timeMoneyMqd() {
    $money = 0;

    // 夜间加价
    if (time() >= strtotime(date("Y-m-d 23:00:00")) || time() < strtotime(date("Y-m-d 6:30:00"))) {
        $money +=3;
    }

    return $money;
}

/**
 * 时间加价
 * @return int
 */
function timeMoneyFn() {
    $money = 0;

    // 夜间加价
    if (time() >= strtotime(date("Y-m-d 22:00:00")) || time() < strtotime(date("Y-m-d 7:00:00"))) {
        $money +=5;
    }

    // 早峰加价
    if (time() >= strtotime(date("Y-m-d 07:00:00")) && time() < strtotime(date("Y-m-d 09:00:00"))) {
        $money +=2;
    }

    // 午峰加价
    if (time() >= strtotime(date("Y-m-d 11:00:00")) && time() < strtotime(date("Y-m-d 13:00:00"))) {
        $money +=2;
    }

    return $money;
}

/**
 * 日期加价
 * @return int
 */
function dateMoney() {
    $money = 0;

    // 节日加价
    if (date("m-d") === '11-11' || date("m-d") === '12-12' || in_array(date("Y-m-d"), config('ps.jieri'))) {
        $money +=3;
    }

    return $money;
}

/**
 * 重量加价
 * @param $weight
 * @return float|int
 */
function weightMoney($weight) {
    $money = 0;

    if ($weight > 5) {
        if ($weight <= 10) {
            $money += ($weight - 5) * 0.5;
        } else {
            $money += 5 * 0.5;
        }
    }

    if ($weight > 10) {
        if ($weight <= 20) {
            $money += ($weight - 10) * 1;
        } else {
            $money += 10 * 1;
        }
    }

    if ($weight > 20) {
        $money += ($weight - 20) * 2;
    }

    return $money;
}

/**
 * （美全达）重量加价
 * @param $weight
 * @return float|int
 */
function weightMoneyMqd($weight) {
    $money = 0;

    if ($weight > 5) {
        if ($weight <= 10) {
            $money += ($weight - 5) * 1;
        } else {
            $money += 5 * 1;
        }
    }

    if ($weight > 10) {
        if ($weight <= 20) {
            $money += ($weight - 10) * 2;
        } else {
            $money += 10 * 2;
        }
    }

    return $money;
}

/**
 * 重量加价
 * @param $weight
 * @return float|int
 */
function weightMoneyFn($weight) {
    $money = 0;

    if ($weight >= 5) {
        if ($weight < 10) {
            $money += ($weight - 4) * 1;
        } else {
            $money += 5 * 1;
        }
    }

    if ($weight >= 10) {
        if ($weight < 15) {
            $money += ($weight - 9) * 2;
        } else {
            $money += 5 * 1;
        }
    }

    if ($weight >= 15) {
        $money += ($weight - 19) * 3;
    }

    return $money;
}

function getShopDistance($shop, $lng, $lat)
{
    $url = "https://restapi.amap.com/v3/distance?origins={$shop->shop_lng},{$shop->shop_lat}&destination={$lng},{$lat}&key=".config('ps.amap.AMAP_APP_KEY1')."&type=1";

    $str = file_get_contents($url);

    $data = json_decode($str, true);

    \Log::info('获取距离结果：', ["shop_id" => $shop->id, "shop_name" => $shop->shop_name, "lng" => $lng, "lat" => $lat, "distance" => $data['results'][0]['distance'] / 1000]);

    return $data['results'][0]['distance'] / 1000;
}

function getShopDistanceV4($shop, $lng, $lat)
{
    $url = "https://restapi.amap.com/v3/direction/walking?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$lng},{$lat}&key=".config('ps.amap.AMAP_APP_KEY1');

    $str = file_get_contents($url);

    $data = json_decode($str, true);

    $distance = ($data['route']['paths'][0]['distance'] ?? 0) / 1000;
    // $distance = 3;

    // $url = "https://apis.map.qq.com/ws/direction/v1/bicycling/?from={$shop->shop_lng},{$shop->shop_lat}&to={$lng},{$lat}&key=SKUBZ-4G73K-EHXJ7-AUB5E-RD5X3-7CBA7";
    // $str = file_get_contents($url);
    // return $str;
    // $data = json_decode($str, true);
    // $distance = $data['routes']['paths'][0]['distance'] / 1000;
    \Log::info('Walking 获取距离结果：', [ $distance ]);

    return $distance;
}

/**
 * 获取距离加价
 * @param $juli
 * @return float|int
 */
function distanceMoney($juli) {
    $money = 0;

    if ($juli > 10) {
        \Log::info('美团获取距离超出10公里', []);
        // return -1;
    }

    if ($juli > 1) {
        if ($juli <= 3) {
            $money += ceil($juli - 1) * 1;
        } else {
            $money += 2 * 1;
        }
    }

    if ($juli > 3) {
        if ($juli <= 5) {
            $money += ceil($juli - 3) * 2;
        } else {
            $money += 2 * 2;
        }
    }

    if ($juli > 5) {
        if ($juli <= 7) {
            $money += ceil($juli - 5) * 3;
        } else {
            $money += 2 * 3;
        }
    }

    if ($juli > 7) {
        $money += ceil($juli - 7) * 5;
    }

    return $money;
}

/**
 * （美全达）获取距离加价
 * @param $juli
 * @return float|int
 */
function distanceMoneyMqd($juli) {
    $money = 0;

    // if ($juli > 10) {
    //     \Log::info('美团获取距离超出10公里', []);
        // return -1;
    // }

    if ($juli > 2) {
        if ($juli <= 5) {
            $money += ceil($juli - 1) * 1;
        } else {
            $money += 3 * 1;
        }
    }

    if ($juli > 5) {
        if ($juli <= 10) {
            $money += ceil($juli - 5) * 2;
        } else {
            $money += 5 * 2;
        }
    }

    if ($juli > 10) {
        $money += ceil($juli - 10) * 3;
    }

    return $money;
}

/**
 * 获取 蜂鸟 距离加价
 * @param $juli
 * @return float|int
 */
function distanceMoneyFn($juli) {
    $money = 0;

    if ($juli > 20) {
        \Log::info('超出10公里', []);
    }

    // [1-3)KM 1元/km/单
    if ($juli >=1) {
        if ($juli < 3) {
            $money += ceil($juli) * 1;
        } else {
            $money += 2 * 1;
        }
    }

    // [3-4)KM 2元/km/单
    if ($juli >=3) {
        if ($juli < 4) {
            $money += ceil($juli - 2) * 2;
        } else {
            $money += 1 * 2;
        }
    }

    // [4-5)KM 2元/km/单
    if ($juli >=4) {
        if ($juli < 5) {
            $money += ceil($juli - 3) * 2;
        } else {
            $money += 1 * 2;
        }
    }

    // [5-6)KM 2元/km/单
    if ($juli >=5) {
        if ($juli < 6) {
            $money += ceil($juli - 4) * 3;
        } else {
            $money += 1 * 3;
        }
    }

    if ($juli >= 6) {
        $money += ceil($juli - 5) * 5;
    }

    return $money;
}

/**
 * 获取基础价格
 * @param $shop_live
 * @return int|mixed
 */
function baseMoney($shop_live) {

    // $start_arr = [ 3 => 6.7, 4 => 6.5, 5 => 6, 6 => 5.5, 7 => 5.2, 11 => 7, 12 => 7, 13 => 6.7 ];
    $start_arr = [ 3 => 6.8, 4 => 6.6, 5 => 6.3, 6 => 5.8, 7 => 5.5, 11 => 7.5, 12 => 7.5, 13 => 6.8 ];

    $b = $start_arr[$shop_live] ?? 7;
    // $b = $b + 2;

    return $b;
}

/**
 * （美全达）获取基础价格
 * @param $shop_live
 * @return int|mixed
 */
function baseMoneyMqd($shop_live) {

    // $start_arr = [ 3 => 6.7, 4 => 6.5, 5 => 6, 6 => 5.5, 7 => 5.2, 11 => 7, 12 => 7, 13 => 6.7 ];
    $start_arr = [ 2 => 6.8, 3 => 6.8, 4 => 6.8, 5 => 6.8, 6 => 6.8, 7 => 6.8, 11 => 6.8, 12 => 6.8, 13 => 6.8 ];

    $b = $start_arr[$shop_live] ?? 7;
    // $b = $b + 2;

    return $b;
}

/**
 * 获取 蜂鸟 基础价格
 * @param $shop_live
 * @return int|mixed
 */
function baseMoneyFn($shop_live) {

    // $start_arr = [ 'S' => 9.3, 'A' => 8.8, 'B' => 8.3, 'C' => 7.8, 'D' => 7.3, 'E' => 6.8 ];
    // $start_arr = [ 'S' => 7.3, 'A' => 7.1, 'B' => 6.9, 'C' => 6.5, 'D' => 6.0, 'E' => 5.7 ];
    $start_arr = [ 'S' => 7.8, 'A' => 7.6, 'B' => 7.4, 'C' => 7, 'D' => 6.5, 'E' => 6.2 ];

    $b = $start_arr[$shop_live] ?? 7.3;
    // $b = $b + 5;

    return $b;
}

function getMoney($shop, $receiver_lng, $receiver_lat) {

    if ( $juli = getJuli($shop, $receiver_lng, $receiver_lat) ) {
        return getLeast();
    }

    $start_arr = [ 3 => 8.7, 4 => 8.5, 5 => 8, 6 => 7.5, 7 => 7.2, 11 => 9, 12 => 9, 13 => 8.7 ];

    $start = isset($start_arr[$shop->city_level]) ?? 9;

    $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=".config('ps.amap.AMAP_APP_KEY1');

    $str = file_get_contents($url);
    $data = json_decode($str, true);
    \Log::info('juli', [$str]);
    if (isset($data['results'][0]['distance'])) {
        return $data['results'][0]['distance'] / 1000;
    }

    return 7;
}

function getJuli($shop, $receiver_lng, $receiver_lat) {

    $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=".config('ps.amap.AMAP_APP_KEY1');

    $str = file_get_contents($url);
    $data = json_decode($str, true);
    \Log::info('juli', [$str]);
    if (isset($data['results'][0]['distance'])) {
        return $data['results'][0]['distance'] / 1000;
    }

    return 1;
}
