<?php

namespace App\Jobs;

use App\Models\OrderDeduction;
use App\Models\Order;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MtLogisticsSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    /**
     * 跑腿订单状态变更，异步任务
     *
     * @return void
     */
    public function __construct(Order $order)
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
        if (in_array($this->order->type, [1,2,3,4,5])) {

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

            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 10;
                $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 45 * 60);
                \Log::info('美团外卖同步预计送达时间结束', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
                // 同步其它状态
                $params_tmp = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => 0
                ];
                $result = $meituan->logisticsSync($params_tmp);
                \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));
                $params_tmp = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => 1
                ];
                $result = $meituan->logisticsSync($params_tmp);
                \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));
                $params_tmp = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => 5
                ];
                $result = $meituan->logisticsSync($params_tmp);
                \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));

            }elseif ($this->order->status == 60) {
                $status = 20;
            }elseif ($this->order->status == 70) {
                $status = 40;
            }

            $params = [
                "order_id" => $this->order->order_id,
                "courier_name" => $this->order->courier_name,
                "courier_phone" => $this->order->courier_phone,
                "logistics_status" => $status
            ];

            \Log::info('美团外卖同步配送信息开始', []);
            $result = $meituan->logisticsSync($params);
            \Log::info('美团外卖同步配送信息结束', compact("params", "result"));
        } elseif ($this->order->type == 11) {
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

            $meituan = app("meiquan");

            $status = 5;

            if ($this->order->status == 40) {
                $status = 10;
            }elseif ($this->order->status == 50) {
                $status = 10;
                $time_result = $meituan->syncEstimateArrivalTime($this->order->order_id, time() + 45 * 60);
                \Log::info('美团外卖同步预计送达时间结束', ['id' => $this->order->id, 'order_id' => $this->order->order_id, 'result' => $time_result]);
                // 同步其它状态
                $params_tmp = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => 0
                ];
                $result = $meituan->logisticsSync($params_tmp);
                \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));
                $params_tmp = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => 1
                ];
                $result = $meituan->logisticsSync($params_tmp);
                \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));
                $params_tmp = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => 5
                ];
                $result = $meituan->logisticsSync($params_tmp);
                \Log::info('美团外卖同步配送信息结束-其它状态', compact("params_tmp", "result"));

            }elseif ($this->order->status == 60) {
                $status = 20;
            }elseif ($this->order->status == 70) {
                $status = 40;
            }

            $shop = Shop::query()->select("id","mt_shop_id")->find($this->order->shop_id);

            if ($shop->mt_shop_id) {
                $key = 'mtwm:shop:auth:'.$shop->mt_shop_id;

                $access_token = Cache::store('redis')->get($key, '');

                if (!$access_token) {
                    $key_ref = 'mtwm:shop:auth:ref:'.$shop->mt_shop_id;
                    $refresh_token = Cache::store('redis')->get($key_ref);
                    if (!$refresh_token) {
                        \Log::info("刷新token不存在|{$shop->mt_shop_id}");
                        $dingding = app("ding");
                        $logs = [
                            "des" => "刷新token不存在",
                            "shop_iid" => $shop->mt_shop_id
                        ];
                        $dingding->sendMarkdownMsgArray("刷新token不存在", $logs);
                        return;
                    }
                    $res = $meituan->waimaiAuthorizeRef($refresh_token);
                    if (!empty($res['access_token'])) {
                        $access_token = $res['access_token'];
                        $refresh_token = $res['refresh_token'];
                        Cache::put($key, $access_token, $res['expires_in'] - 100);
                        Cache::forever($key_ref, $refresh_token);
                    }
                }

                $params = [
                    "order_id" => $this->order->order_id,
                    "courier_name" => $this->order->courier_name,
                    "courier_phone" => $this->order->courier_phone,
                    "logistics_status" => $status,
                    "access_token" => $access_token,
                    "app_poi_code" => $shop->mt_shop_id,
                ];

                \Log::info('美团外卖同步配送信息开始', []);
                $result = $meituan->logisticsSync($params);
                \Log::info('美团外卖同步配送信息结束', compact("params", "result"));
            }
        }

        // 同步外卖订单跑腿费用
        if ($this->order->status == 70) {
            if ($wm = WmOrder::where('order_id', $this->order->order_id)->first()) {
                $reduce = OrderDeduction::where('order_id', $this->order->id)->sum('money');
                $running_money = $reduce + $this->order->money;
                $wm->running_fee = $running_money;
                $wm->save();
                $this->log('同步跑腿价格到外卖订单', "外卖订单号:{$this->order->order_id}");
            }
        }
    }

    public function log($name, $text, $data = [])
    {
        Log::info("[JOB跑腿订单状态变更|$name]-$text", $data);
    }
}
