<?php

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

/**
 *
 */
function gd2bd($lng,$lat)
{
    $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
    $x = $lng;
    $y = $lat;
    $z = sqrt($x * $x +$y * $y) - 0.00002 * sin($y * $x_pi);
    $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
    $data['lng'] = $z * cos($theta) +0.0065;
    $data['lat'] = $z * sin($theta)+ 0.006;
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

    if ($weight >= 20) {
        $money += ($weight - 19) * 3;
    }

    return $money;
}

function getShopDistance($shop, $lng, $lat)
{
    $url = "https://restapi.amap.com/v3/distance?origins={$shop->shop_lng},{$shop->shop_lat}&destination={$lng},{$lat}&key=59c3b9c0a69978649edb06bbaccccbe9&type=1";

    $str = file_get_contents($url);

    $data = json_decode($str, true);

    \Log::info('获取距离结果：', ["shop_id" => $shop->id, "shop_name" => $shop->shop_name, "lng" => $lng, "lat" => $lat, "distance" => $data['results'][0]['distance'] / 1000]);

    return $data['results'][0]['distance'] / 1000;
}

function getShopDistanceV4($shop, $lng, $lat)
{
    $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$lng},{$lat}&key=59c3b9c0a69978649edb06bbaccccbe9";

    $str = file_get_contents($url);

    $data = json_decode($str, true);

    $distance = $data['data']['paths'][0]['distance'] / 1000 ?? 0;

    if ($distance === 0) {
        \Log::error('获取距离结果-出错-默认 1 ：', ["shop_id" => $shop->id, "shop_name" => $shop->shop_name, "lng" => $lng, "lat" => $lat, "distance" => $distance]);
        $distance = 1;
    } else {
        \Log::info('获取距离结果：', ["shop_id" => $shop->id, "shop_name" => $shop->shop_name, "lng" => $lng, "lat" => $lat, "distance" => $distance]);
    }


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
 * 获取 蜂鸟 距离加价
 * @param $juli
 * @return float|int
 */
function distanceMoneyFn($juli) {
    $money = 0;

    if ($juli > 20) {
        \Log::info('超出10公里', []);
    }

    if ($juli >=3) {
        if ($juli < 5) {
            $money += ceil($juli - 2) * 2;
        } else {
            $money += ceil($juli - 2) * 2;
        }
    }

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

    $start_arr = [ 3 => 6.7, 4 => 6.5, 5 => 6, 6 => 5.5, 7 => 5.2, 11 => 7, 12 => 7, 13 => 6.7 ];

    return $start_arr[$shop_live] ?? 7;
}

/**
 * 获取 蜂鸟 基础价格
 * @param $shop_live
 * @return int|mixed
 */
function baseMoneyFn($shop_live) {

    $start_arr = [ 'S' => 9.3, 'A' => 8.8, 'B' => 8.3, 'C' => 7.8, 'D' => 7.3, 'E' => 6.8 ];

    return $start_arr[$shop_live] ?? 7.3;
}

function getMoney($shop, $receiver_lng, $receiver_lat) {

    if ( $juli = getJuli($shop, $receiver_lng, $receiver_lat) ) {
        return getLeast();
    }

    $start_arr = [ 3 => 8.7, 4 => 8.5, 5 => 8, 6 => 7.5, 7 => 7.2, 11 => 9, 12 => 9, 13 => 8.7 ];

    $start = isset($start_arr[$shop->city_level]) ?? 9;

    $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9";
    // $url = "https://restapi.amap.com/v3/distance?origins={$shop->shop_lng},{$shop->shop_lat}&destination={$model->receiver_lng},{$model->receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9&type=1";

    $str = file_get_contents($url);
    $data = json_decode($str, true);
    \Log::info('juli', [$str]);
    if (isset($data['results'][0]['distance'])) {
        return $data['results'][0]['distance'] / 1000;
    }

    return 7;
}

function getJuli($shop, $receiver_lng, $receiver_lat) {

    $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9";
    // $url = "https://restapi.amap.com/v3/distance?origins={$shop->shop_lng},{$shop->shop_lat}&destination={$model->receiver_lng},{$model->receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9&type=1";

    $str = file_get_contents($url);
    $data = json_decode($str, true);
    \Log::info('juli', [$str]);
    if (isset($data['results'][0]['distance'])) {
        return $data['results'][0]['distance'] / 1000;
    }

    return 0;
}