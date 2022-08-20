<?php

namespace App\Jobs\Timer;

use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\Shop;
use Hhxsv5\LaravelS\Swoole\Timer\CronJob;

class ShipperLogisticsSyncTimer extends CronJob
{
    public function interval()
    {
        return 60000 * 10;// 每60秒运行一次
    }

    public function isImmediate()
    {
        return true;// 是否立即执行第一次，false则等待间隔时间后执行第一次
    }

    public function run()
    {
        $orders = Order::query()->whereIn('status', [50, 60])->orderBy('id')->get();
        \Log::info("同步骑手位置|开始");

        if (!empty($orders)) {
            $jay = app("jay"); // 3
            $minkang = app("minkang"); // 4
            $qinqu = app("qinqu"); // 5
            $ele = app("ele"); // 21
            $shangou = app("meiquan"); // 31
            $canyin = app("mtkf"); // 7

            foreach ($orders as $order) {
                if ($order->ps == 0) {
                    \Log::info("同步骑手位置异常|订单没有配送平台|id:{$order->id},order_id:{$order->order_id}");
                }
                $codes = [ 1 => '10032', 2 => '10004', 3 => '10003', 4 => '10017', 5 => '10002', 6 => '10005', 7 => '10001',];
                $mt_status = $order->status == 50 ? 10 : 20;
                $shipper_name = '';
                $shipper_phone = '';
                $longitude = 0;
                $latitude = 0;
                if ($order->ps === 1) {
                    $meituan = app("meituan");
                    $shipper_res = $meituan->location(['delivery_id' => $order->order_id, 'mt_peisong_id' => $order->mt_order_id]);
                    if (!empty($shipper_res['data']['lng']) && !empty($shipper_res['data']['lat'])) {
                        $longitude = $shipper_res['data']['lng'] / 1000000;
                        $latitude = $shipper_res['data']['lat'] / 1000000;
                    } else {
                        \Log::info("同步骑手位置异常|美团未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                } else if ($order->ps === 2) {
                    $fengniao = app("fengniao");
                    $shipper_res = $fengniao->carrier(['partner_order_code' => $order->order_id]);
                    if (!empty($shipper_res['data']['longitude']) && !empty($shipper_res['data']['latitude'])) {
                        $shipper_name = $shipper_res['data']['carrierName'];
                        $shipper_phone = $shipper_res['data']['carrierPhone'];
                        $longitude = $shipper_res['data']['longitude'];
                        $latitude = $shipper_res['data']['latitude'];
                    } else {
                        \Log::info("同步骑手位置异常|蜂鸟未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                } else if ($order->ps === 3) {
                    // 百度坐标
                    if ($order->shipper_type_ss) {
                        $shansong = new ShanSongService(config('ps.shansongservice'));
                    } else {
                        $shansong = app("shansong");
                    }
                    $shipper_res = $shansong->carrier($order->ss_order_id);
                    if (!empty($shipper_res['data']['longitude']) && !empty($shipper_res['data']['latitude'])) {
                        $shipper_name = $shipper_res['data']['name'];
                        $shipper_phone = $shipper_res['data']['mobile'];
                        $longitude = $shipper_res['data']['longitude'];
                        $latitude = $shipper_res['data']['latitude'];
                        $ss_to = coordinate_switch($longitude, $latitude);
                        if (!empty($ss_to['longitude'])) {
                            $longitude = $ss_to['longitude'];
                            $latitude = $ss_to['latitude'];
                        }
                    } else {
                        \Log::info("同步骑手位置异常|闪送未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                } else if ($order->ps === 4) {
                    $meiquanda = app("meiquanda");
                    $shipper_res = $meiquanda->getCourierTag($order->mqd_order_id);
                    if (!empty($shipper_res['data']['longitude']) && !empty($shipper_res['data']['latitude'])) {
                        $longitude = $shipper_res['data']['longitude'];
                        $latitude = $shipper_res['data']['latitude'];
                    } else {
                        \Log::info("同步骑手位置异常|美全达未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                } else if ($order->ps === 5) {
                    if ($order->shipper_type_dd) {
                        $config = config('ps.dada');
                        $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                        $dada = new DaDaService($config);
                    } else {
                        $dada = app("dada");
                    }
                    $shipper_res = $dada->getOrderInfo($order->order_id);
                    if (!empty($shipper_res['result']['transporterLng']) && !empty($shipper_res['result']['transporterLat'])) {
                        $shipper_name = $shipper_res['result']['transporterName'];
                        $shipper_phone = $shipper_res['result']['transporterPhone'];
                        $longitude = $shipper_res['result']['transporterLng'];
                        $latitude = $shipper_res['result']['transporterLat'];
                    } else {
                        \Log::info("同步骑手位置异常|达达未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                } else if ($order->ps === 6) {
                    // 百度坐标
                    $uu = app("uu");
                    $shipper_res = $uu->getOrderInfo($order->order_id);
                    if (!empty($shipper_res['to_lat']) && !empty($shipper_res['to_lng'])) {
                        $shipper_name = $shipper_res['driver_name'];
                        $shipper_phone = $shipper_res['driver_mobile'];
                        $longitude = $shipper_res['to_lng'];
                        $latitude = $shipper_res['to_lat'];
                        $uu_to = coordinate_switch($longitude, $latitude);
                        if (!empty($uu_to['longitude'])) {
                            $longitude = $uu_to['longitude'];
                            $latitude = $uu_to['latitude'];
                        }
                    } else {
                        \Log::info("同步骑手位置异常|UU未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                } else if ($order->ps === 7) {
                    if ($order->shipper_type_sf) {
                        $sf = app("shunfengservice");
                    } else {
                        $sf = app("shunfeng");
                    }
                    $shipper_res = $sf->position($order->sf_order_id);
                    if (!empty($shipper_res['result']['rider_lng']) && !empty($shipper_res['result']['rider_lat'])) {
                        $shipper_name = $shipper_res['result']['rider_name'];
                        $shipper_phone = $shipper_res['result']['rider_phone'];
                        $longitude = $shipper_res['result']['rider_lng'];
                        $latitude = $shipper_res['result']['rider_lat'];
                    } else {
                        \Log::info("同步骑手位置异常|顺丰未返回经纬度|id:{$order->id},order_id:{$order->order_id}", [$shipper_res]);
                        return;
                    }
                }
                if (!$longitude || !$latitude) {
                    \Log::info("同步骑手位置异常|经纬度不存在|id:{$order->id},order_id:{$order->order_id}");
                    return;
                }
                \Log::info("同步骑手位置经纬度|经度：{$longitude},纬度:{$latitude}|id:{$order->id},order_id:{$order->order_id}");
                if (in_array($order->type, [3,4,5,31])) {
                    $mt_params = [
                        "order_id" => $order->order_id,
                        "courier_name" => $shipper_name ?: $order->courier_name,
                        "courier_phone" => $shipper_phone ?: $order->courier_phone,
                        "logistics_status" => $mt_status,
                        "third_carrier_order_id" => $order->peisong_id,
                        'logistics_provider_code' => $codes[$order->ps ?: 4],
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ];
                    if ($order->type == 3) {
                        $res = $jay->logisticsSync($mt_params);
                        \Log::info("同步骑手位置结果|洁爱眼|id:{$order->id},order_id:{$order->order_id}|结果", [$res]);
                    }else if ($order->type == 4) {
                        $res = $minkang->logisticsSync($mt_params);
                        \Log::info("同步骑手位置结果|民康|id:{$order->id},order_id:{$order->order_id}|结果", [$res]);
                    }else if ($order->type == 5) {
                        $res = $qinqu->logisticsSync($mt_params);
                        \Log::info("同步骑手位置结果|寝趣|id:{$order->id},order_id:{$order->order_id}|结果", [$res]);
                    }else if ($order->type == 31) {
                        if ($shop = Shop::find($order->shop_id)) {
                            $mt_params['access_token'] = $shangou->getShopToken($shop->waimai_mt);
                            $mt_params['app_poi_code'] = $shop->waimai_mt;
                            $res = $shangou->logisticsSync($mt_params);
                            \Log::info("同步骑手位置结果|闪购|id:{$order->id},order_id:{$order->order_id}|结果", [$res]);
                        } else {
                            \Log::info("同步骑手位置异常|闪购|未找到门店|id:{$order->id},order_id:{$order->order_id}");
                        }
                    }
                } else if ($order->type == 21) {
                    $ele_params = [
                        'order_id' => $order->order_id,
                        'location' => [
                            'UTC' => time(),
                            'altitude' => 20,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]
                    ];
                    $res = $ele->selfDeliveryLocationSync($ele_params);
                    \Log::info("同步骑手位置结果|饿了么|id:{$order->id},order_id:{$order->order_id}|结果", [$res]);

                } else if ($order->type == 7) {
                    $cy_params = [
                        "orderId" => $order->order_id,
                        "courierName" => $shipper_name ?: $order->courier_name,
                        "courierPhone" => $shipper_phone ?: $order->courier_phone,
                        "logisticsStatus" => $mt_status,
                        "thirdCarrierId" => $order->peisong_id,
                        'thirdLogisticsId' => $codes[$order->ps ?: 4],
                        'latitude' => $order->courier_lat,
                        'longitude' => $order->courier_lng,
                        'backFlowTime' => time()
                    ];

                    $res = $canyin->logistics_sync($cy_params, $order->shop_id);
                    \Log::info("同步骑手位置结果|餐饮|id:{$order->id},order_id:{$order->order_id}|结果", [$res]);
                }
            }
        } else {
            \Log::info("同步骑手位置|没有订单-结束");
        }
        \Log::info("同步骑手位置|结束");
    }
}
