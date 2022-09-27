<?php

namespace App\Services;

use App\Exceptions\HttpException;
use App\Exceptions\InvalidRequestException;
use App\Exceptions\MessageException;
use App\Models\Shop;
use function GuzzleHttp\Psr7\str;

class Delivery
{

    public function getMoney(Shop $shop, $receiver_lng, $receiver_lat, $goods_weight)
    {
        $juli = $this->getJuli($shop, $receiver_lng, $receiver_lat);

        return $this->getMeituan($shop, $juli, $goods_weight);
    }

    public function getJuli($shop, $receiver_lng, $receiver_lat) {

        try {

            // $url = "https://restapi.amap.com/v4/direction/bicycling?origin={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9";
            $url = "https://restapi.amap.com/v3/distance?origins={$shop->shop_lng},{$shop->shop_lat}&destination={$receiver_lng},{$receiver_lat}&key=59c3b9c0a69978649edb06bbaccccbe9&type=1";

            $str = file_get_contents($url);

            $data = json_decode($str, true);

            \Log::info('获取距离结果：', [$data['results'][0]['distance'] / 1000]);

            return $data['results'][0]['distance'] / 1000;

        } catch (\Exception $e) {
            \Log::info('请求获取距离失败', []);
            return 1;
            // throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // public function getLeast($juli) {
    //     $meituan = $this->getMeituan($juli);
    //     $dada = $this->getMeituan($juli);
    // }

    public function getMeituan($shop, $juli, $goods_weight)
    {

        $start_arr = [ 3 => 8.7, 4 => 8.5, 5 => 8, 6 => 7.5, 7 => 7.2, 11 => 9, 12 => 9, 13 => 8.7 ];

        $money = $start_arr[$shop->city_level] ?? 9;

        if ($juli > 10) {
            throw new MessageException("距离 {$juli}KM，无法配送");
        }

        // 距离加价
        if ($juli > 1 && $juli <= 3) {
            $money += 1;
        } elseif ($juli > 3 && $juli <= 5) {
            $money += 2;
        } elseif ($juli > 5 && $juli <= 7) {
            $money += 3;
        } elseif ($juli > 7 && $juli <= 10) {
            $money += 5;
        }

        // 重量加价
        $weight_money = 0;
        if ($goods_weight > 5) {

        }


        // 夜间加价
        if (time() >= strtotime(date("Y-m-d 21:00:00")) || time() < strtotime(date("Y-m-d 6:00:00"))) {
            $money +=3;
        }

        // 午峰加价
        if (time() >= strtotime(date("Y-m-d 11:00:00")) && time() < strtotime(date("Y-m-d 13:00:00"))) {
            $money +=2;
        }

        // 节日加价
        if (date("m-d") === '11-11' || date("m-d") === '12-12' || in_array(date("Y-m-d"), config('ps.jieri'))) {
            $money +=3;
        }

        return $money;
    }

}
