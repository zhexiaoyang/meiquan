<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MtLogisticsSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    protected $order;

    /**
     * 跑腿订单状态变更，异步任务
     *
     * @return void
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 10001-顺丰, 10002-达达, 10003-闪送, 10004-蜂鸟, 10005 UU跑腿,10017-其他,10032-美团跑腿。
        $codes = [ 1 => '10032', 2 => '10004', 3 => '10003', 4 => '10017', 5 => '10002', 6 => '10005', 7 => '10001', 8 => '10032'];
        if (in_array($this->order->type, [1,2,3,4,5])) {
            // 同步民康-订单状态
            $type = $this->order->type;
            if ($type === 1) {
                $meituan = app("yaojite");
            } elseif ($type === 2) {
                $meituan = app("mrx");
            } elseif ($type === 3) {
                $meituan = app("jay");
            } elseif ($type === 4) {
                $meituan = app("minkang");
            } elseif ($type === 5) {
                $meituan = app("qinqu");
            }

            $status = 5;
            // logistics_provider_code
            if ($this->order->status == 40 || $this->order->status == 50) {
                $status = 10;
                $latitude = $this->order->courier_lat;
                $longitude = $this->order->courier_lng;
                if (!$latitude || !$longitude) {
                    $shop = DB::table('shops')->select('shop_lng', 'shop_lat')->find($this->order->shop_id);
                    $locations = rider_location($shop->shop_lng, $shop->shop_lat);
                    $longitude = $locations['lng'];
                    $latitude = $locations['lat'];
                }
                $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 50 * 60);
                \Log::info('美团外卖民康同步预计送达时间结束|骑手接单', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
                $params = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => $status,
                    "third_carrier_order_id" => $this->order->peisong_id ?: $this->order->order_id,
                    'logistics_provider_code' => $codes[$this->order->ps ?: 4],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
                $result = $meituan->logisticsSync($params);
                \Log::info('美团外卖民康同步配送信息结束|骑手接单', compact("params", "result"));
            }elseif ($this->order->status == 60) {
                $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 35 * 60);
                \Log::info('美团外卖民康同步预计送达时间结束|骑手取货', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
                $status = 15;
                $latitude = $this->order->courier_lat;
                $longitude = $this->order->courier_lng;
                $params = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => $status,
                    "third_carrier_order_id" => $this->order->peisong_id ?: $this->order->order_id,
                    'logistics_provider_code' => $codes[$this->order->ps ?: 4],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
                $result = $meituan->logisticsSync($params);
                \Log::info('美团外卖民康同步配送信息结束|骑手到店', compact("params", "result"));
                sleep(2);
                $status = 20;
                $params = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => $status,
                    "third_carrier_order_id" => $this->order->peisong_id ?: $this->order->order_id,
                    'logistics_provider_code' => $codes[$this->order->ps ?: 4],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
                $result = $meituan->logisticsSync($params);
                \Log::info('美团外卖民康同步配送信息结束|骑手取货', compact("params", "result"));
            }elseif ($this->order->status == 70) {
                $status = 40;
                $latitude = $this->order->courier_lat;
                $longitude = $this->order->courier_lng;
                $params = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => $status,
                    "third_carrier_order_id" => $this->order->peisong_id ?: $this->order->order_id,
                    'logistics_provider_code' => $codes[$this->order->ps ?: 4],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
                $result = $meituan->logisticsSync($params);
                \Log::info('美团外卖民康同步配送信息结束|骑手送达', compact("params", "result"));
            }

            // $params = [
            //     "order_id" => $this->order->order_id,
            //     "courier_name" => $this->order->courier_name,
            //     "courier_phone" => $this->order->courier_phone,
            //     "logistics_status" => $status
            // ];
            //
            // \Log::info('美团外卖同步配送信息开始', []);
            // $result = $meituan->logisticsSync($params);
            // \Log::info('美团外卖同步配送信息结束', compact("params", "result"));
        } elseif ($this->order->type == 11) {
            // 同步药柜订单状态
            $yaogui = app("yaogui");

            $status = 5;

            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 15;
            }elseif ($this->order->status == 60) {
                $status = 20;
            }elseif ($this->order->status == 70) {
                $status = 40;
            }

            $params = [
                "courierName" =>  $this->order->courier_name,
                "orderNo" => $this->order->order_id,
                "courierPhone" => $this->order->courier_phone,
                "logisticsStatus" => $status
            ];
            \Log::info('药柜同步配送信息开始', []);
            $result = $yaogui->logisticsOrder($params);
            \Log::info('药柜同步配送信息结束', compact("params", "result"));
        } elseif ($this->order->type == 21) {
            // 同步饿了么订单状态
            \Log::info("[同步配送信息-饿了么]-[订单号:{$this->order->order_id}]-开始");
            $ele = app("ele");
            $params = [
                "order_id" =>  $this->order->order_id,
                "name" => $this->order->courier_name,
                "phone" => $this->order->courier_phone,
            ];
            $result = $ele->deliveryStatus($params);
            \Log::info("[同步配送信息-饿了么]-[订单号:{$this->order->order_id}]-结束", compact("params", "result"));
            if (in_array($this->order->status, [40, 50, 60])) {
                $res = $ele->sendoutOrder($this->order->order_id);
            } elseif ($this->order->status == 70) {
                $res = $ele->completeOrder($this->order->order_id);
            }
            \Log::info("[同步配送订单状态-饿了么]-[订单号:{$this->order->order_id}]-结果", [$res]);
        } elseif ($this->order->type == 31) {
            // 同步美团服务商-订单状态
            $meituan = app("meiquan");

            $status = 5;
            $shop = DB::table('shops')->select("id","mt_shop_id","waimai_mt")->find($this->order->shop_id);
            // $shop = Shop::query()->select("id","mt_shop_id","waimai_mt")->find($this->order->shop_id);

            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 10;
                $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 50 * 60, $shop->waimai_mt);
                \Log::info('美团外卖同步预计送达时间结束', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
                // 同步其它状态
                // $params_tmp = [
                //     "order_id" => $this->order->order_id,
                //     "courier_name" => $this->order->courier_name,
                //     "courier_phone" => $this->order->courier_phone,
                //     "logistics_status" => 0
                // ];
                // $result = $meituan->logisticsSync($params_tmp);
                // \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));
                // $params_tmp = [
                //     "order_id" => $this->order->order_id,
                //     "courier_name" => $this->order->courier_name,
                //     "courier_phone" => $this->order->courier_phone,
                //     "logistics_status" => 1
                // ];
                // $result = $meituan->logisticsSync($params_tmp);
                // \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));
                // $params_tmp = [
                //     "order_id" => $this->order->order_id,
                //     "courier_name" => $this->order->courier_name,
                //     "courier_phone" => $this->order->courier_phone,
                //     "logistics_status" => 5
                // ];
                // $result = $meituan->logisticsSync($params_tmp);
                // \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));

            }elseif ($this->order->status == 60) {
                $status = 20;
                $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 25 * 60, $shop->waimai_mt);
                \Log::info('美团外卖同步预计送达时间结束', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
            }elseif ($this->order->status == 70) {
                $status = 40;
            }

            if ($shop->mt_shop_id) {
                $params = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => $status,
                    "access_token" => $meituan->getShopToken($shop->waimai_mt),
                    "app_poi_code" => $shop->waimai_mt,
                    "third_carrier_order_id" => $this->order->peisong_id ?: $this->order->order_id,
                    'logistics_provider_code' => $codes[$this->order->ps ?: 4],
                    'latitude' => $this->order->courier_lat,
                    'longitude' => $this->order->courier_lng,
                ];

                $result = $meituan->logisticsSync($params);
                \Log::info('美团外卖服务商同步配送信息结束', compact("params", "result"));
            }
        } elseif ($this->order->type == 7) {
            // 同步美团餐饮-订单状态
            $order_id = $this->order->order_id;
            \Log::info("美团餐饮同步配送信息{$order_id}-开始");
            \Log::info("美团餐饮同步配送信息{$order_id}-订单状态：" . $this->order->status);
            $status = 5;
            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 10;
                // $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 45 * 60);
                // \Log::info('美团外卖同步预计送达时间结束', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
            }elseif ($this->order->status == 60) {
                $status = 20;
            }elseif ($this->order->status == 70) {
                $status = 40;
            }
            // $shop = Shop::query()->select("id","mt_shop_id")->find($this->order->shop_id);
            \Log::info("美团餐饮同步配送信息{$order_id}-美团订单状态：" . $status);
            $params = [
                "orderId" => $this->order->order_id,
                "courierName" => $this->order->courier_name,
                "courierPhone" => $this->order->courier_phone,
                "logisticsStatus" => $status,
                // "access_token" => $access_token,
                // "app_poi_code" => $shop->mt_shop_id,
                "thirdCarrierId" => $this->order->peisong_id ?: $this->order->order_id,
                'thirdLogisticsId' => $codes[$this->order->ps ?: 4],
                'latitude' => $this->order->courier_lat,
                'longitude' => $this->order->courier_lng,
                'backFlowTime' => time()
            ];

            $meituan = app("mtkf");
            $result = $meituan->logistics_sync($params, $this->order->shop_id);
            \Log::info("美团餐饮同步配送信息{$order_id}-结束");
            \Log::info("美团餐饮同步配送信息{$order_id}参数信息", compact("params", "result"));
        }

        // 同步外卖订单跑腿费用
        if ($this->order->status == 70) {
            if (DB::table('wm_orders')->find($this->order->wm_id)) {
                $reduce = DB::table('order_deductions')->where('order_id', $this->order->id)->sum('money');
                DB::table('wm_orders')->where('id', $this->order->wm_id)->update([
                    'running_fee' => $this->order->money,
                    'running_deduction_fee' => $reduce,
                    'running_service_fee' => $this->order->service_fee,
                ]);
                $this->log('同步跑腿价格到外卖订单', "外卖订单号:{$this->order->order_id}");
            }
        }
    }

    public function log($name, $text, $data = [])
    {
        Log::info("[JOB跑腿订单状态变更|$name]-$text", $data);
    }
}
