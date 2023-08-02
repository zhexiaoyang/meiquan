<?php

namespace App\Console\Commands;

use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\OrderLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunningOrderStatusCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'running-order-status-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // $orders = Order::whereIn("status", [20, 50, 60])->where('push_at', '<', date("Y-m-d H:i:s", time() - 3600 * 2))->orderBy('id')->get();
        // 2小时未接单
        $orders = Order::where("status", 20)->where('push_at', '<', date("Y-m-d H:i:s", time() - 3600 * 2))->orderBy('id')->get();
        $this->info("未接单数量：" . $orders->count());
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $this->cancel_order($order);
                $this->info($order->order_id);
            }
        }
        // 已接单-2天未完成
        $orders = Order::select('id','order_id','status','pay_status','ps','receive_at','created_at')->where("status", 50)->where('receive_at', '<', date("Y-m-d H:i:s", time() - 3600 * 24))->orderBy('id')->get();
        $this->info("接单数量：" . $orders->count());
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->pay_status === 1 && $order->ps !== 0) {
                    $this->info("{$order->order_id}|{$order->receive_at}|{$order->created_at}");
                    $update_data = [];
                    if ($order->ps == 1) {
                        $update_data = ['status' => 70, 'mt_status' => 70];
                    } elseif ($order->ps == 2) {
                        $update_data = ['status' => 70, 'fn_status' => 70];
                    } elseif ($order->ps == 3) {
                        $update_data = ['status' => 70, 'ss_status' => 70];
                    } elseif ($order->ps == 4) {
                        $update_data = ['status' => 70, 'mqd_status' => 70];
                    } elseif ($order->ps == 5) {
                        $update_data = ['status' => 70, 'dd_status' => 70];
                    } elseif ($order->ps == 6) {
                        $update_data = ['status' => 70, 'uu_status' => 70];
                    } elseif ($order->ps == 7) {
                        $update_data = ['status' => 70, 'sf_status' => 70];
                    } elseif ($order->ps == 8) {
                        $update_data = ['status' => 70, 'zb_status' => 70];
                    }
                    if (!empty($update_data)) {
                        Order::where('id', $order->id)->update($update_data);
                    }
                }
            }
        }
        // 已取货-2天未完成
        $orders = Order::select('id','order_id','status','pay_status','ps','receive_at','created_at')->where("status", 60)->where('take_at', '<', date("Y-m-d H:i:s", time() - 3600 * 24))->orderBy('id')->get();
        $this->info("取货数量：" . $orders->count());
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->pay_status === 1 && $order->ps !== 0) {
                    $this->info("{$order->order_id}|{$order->receive_at}|{$order->created_at}");
                    $update_data = [];
                    if ($order->ps == 1) {
                        $update_data = ['status' => 70, 'mt_status' => 70];
                    } elseif ($order->ps == 2) {
                        $update_data = ['status' => 70, 'fn_status' => 70];
                    } elseif ($order->ps == 3) {
                        $update_data = ['status' => 70, 'ss_status' => 70];
                    } elseif ($order->ps == 4) {
                        $update_data = ['status' => 70, 'mqd_status' => 70];
                    } elseif ($order->ps == 5) {
                        $update_data = ['status' => 70, 'dd_status' => 70];
                    } elseif ($order->ps == 6) {
                        $update_data = ['status' => 70, 'uu_status' => 70];
                    } elseif ($order->ps == 7) {
                        $update_data = ['status' => 70, 'sf_status' => 70];
                    } elseif ($order->ps == 8) {
                        $update_data = ['status' => 70, 'zb_status' => 70];
                    }
                    if (!empty($update_data)) {
                        Order::where('id', $order->id)->update($update_data);
                    }
                }
            }
        }
    }

    public function cancel_order(Order $order)
    {
        if (in_array($order->mt_status, [20, 30])) {
            $meituan = app("meituan");
            $result = $meituan->delete([
                'delivery_id' => $order->delivery_id,
                'mt_peisong_id' => $order->mt_order_id,
                'cancel_reason_id' => 399,
                'cancel_reason' => '其他原因',
            ]);
            if ($result['code'] == 0) {
                $order->status = 99;
                $order->mt_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->fn_status, [20, 30])) {
            $fengniao = app("fengniao");
            $result = $fengniao->cancelOrder([
                'partner_order_code' => $order->order_id,
                'order_cancel_reason_code' => 2,
                'order_cancel_code' => 9,
                'order_cancel_time' => time() * 1000,
            ]);
            if ($result['code'] == 200) {
                $order->status = 99;
                $order->fn_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->ss_status, [20, 30])) {
            if ($order->shipper_type_ss) {
                $shansong = new ShanSongService(config('ps.shansongservice'));
            } else {
                $shansong = app("shansong");
            }
            $result = $shansong->cancelOrder($order->ss_order_id);
            if ($result['status'] == 200) {
                $order->status = 99;
                $order->ss_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->mqd_status, [20, 30])) {
            $meiquanda = app("meiquanda");
            $result = $meiquanda->repealOrder($order->mqd_order_id);
            if ($result['code'] == 100) {
                $order->status = 99;
                $order->mqd_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->dd_status, [20, 30])) {
            if ($order->shipper_type_dd) {
                $config = config('ps.dada');
                $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                $dada = new DaDaService($config);
            } else {
                $dada = app("dada");
            }
            $result = $dada->orderCancel($order->order_id);
            if ($result['code'] == 0) {
                $order->status = 99;
                $order->dd_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->uu_status, [20, 30])) {
            $uu = app("uu");
            $result = $uu->cancelOrder($order);
            if ($result['return_code'] == 'ok') {
                $order->status = 99;
                $order->uu_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
            }
        }
        if (in_array($order->sf_status, [20, 30])) {
            if ($order->shipper_type_sf) {
                $sf = app("shunfengservice");
            } else {
                $sf = app("shunfeng");
            }
            $result = $sf->cancelOrder($order);
            if ($result['error_code'] == 0) {
                $order->status = 99;
                $order->sf_status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "超过2小时无人接单，取消订单"
                ]);
                // 顺丰跑腿运力
                $sf_delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                // 写入顺丰取消足迹
                if ($sf_delivery) {
                    try {
                        $sf_delivery->update([
                            'status' => 99,
                            'cancel_at' => date("Y-m-d H:i:s"),
                            'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        ]);
                        OrderDeliveryTrack::firstOrCreate(
                            [
                                'delivery_id' => $sf_delivery->id,
                                'status' => 99,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                            ], [
                                'order_id' => $sf_delivery->order_id,
                                'wm_id' => $sf_delivery->wm_id,
                                'delivery_id' => $sf_delivery->id,
                                'status' => 99,
                                'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                            ]
                        );
                    } catch (\Exception $exception) {
                        Log::info("自有达达-接单回调取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        $this->ding_error("自有达达-接单回调取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                    }
                }
            }
        }
    }
}
