<?php

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
 * 获取距离加价
 * @param $shop
 * @param $lan
 * @param $lat
 * @return bool|int
 */
function distanceMoney($shop, $lan, $lat) {
    $money = 0;

    try {

        // $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9";
        $url = "https://restapi.amap.com/v3/distance?origins={$shop->shop_lng},{$shop->shop_lat}&destination={$lan},{$lat}&key=59c3b9c0a69978649edb06bbaccccbe9&type=1";

        $str = file_get_contents($url);

        $data = json_decode($str, true);

        \Log::info('获取距离结果：', [$data['results'][0]['distance'] / 1000]);

        $juli = $data['results'][0]['distance'] / 1000;

        if ($juli > 10) {
            \Log::info('超出10公里', []);
            return -1;
        }

        if ($juli > 1 && $juli <= 3) {
            $money += 1;
        } elseif ($juli > 3 && $juli <= 5) {
            $money += 2;
        } elseif ($juli > 5 && $juli <= 7) {
            $money += 3;
        } elseif ($juli > 7 && $juli <= 10) {
            $money += 5;
        }

    } catch (\Exception $e) {
        \Log::info('请求获取距离失败', []);
        return -2;
    }

    return $money;
}

/**
 * 获取基础价格
 * @param $shop_live
 * @return int|mixed
 */
function baseMoney($shop_live) {

    $start_arr = [ 3 => 8.7, 4 => 8.5, 5 => 8, 6 => 7.5, 7 => 7.2, 11 => 9, 12 => 9, 13 => 8.7 ];

    return $start_arr[$shop_live] ?? 9;
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