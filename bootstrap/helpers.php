<?php

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