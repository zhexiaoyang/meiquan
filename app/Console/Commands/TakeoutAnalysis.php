<?php

namespace App\Console\Commands;

use App\Models\WmAnalysis;
use App\Models\WmOrder;
use Illuminate\Console\Command;

class TakeoutAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'set-takeout-analysis';

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
        $date = date("Y-m-d", time() - 86400);
        if (WmAnalysis::where('date', $date)->get()->count() > 0) {
            \Log::info("TakeoutAnalysis已经计算过了");
            $this->info("TakeoutAnalysis已经计算过了");
            return ;
        }
        $orders = WmOrder::with(['running' => function($query) {
            $query->select('id', 'wm_id', 'status', 'money','shipper_type_ss','shipper_type_dd','shipper_type_zb','shipper_type_sf');
        }])->select('id', 'shop_id', 'poi_receive', 'original_price', 'prescription_fee', 'vip_cost', 'status', 'finish_at', 'cancel_at', 'platform','operate_service_fee')
            ->where('created_at', '>=', $date)
            ->where('created_at', '<', date("Y-m-d", strtotime($date) + 86400))->get();
        $this->info('总数：' . $orders->count());
        $data = [];
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $data[$order->shop_id][] = $order;
            }
            $this->info('门店总数：' . count($data));
            foreach ($data as $shop_id => $item) {
                $this->info('门店：' . $shop_id);
                $order_total_number = 0;
                $order_effective_number = 0;
                $order_cancel_number = 0;
                $sales_volume = 0;
                $order_receipts = 0;
                $product_cost = 0;
                $running_money_total = 0;
                $prescription = 0;
                $profit = 0;
                $operate_service = 0;

                $sales_volume1 = 0;
                $order_receipts1 = 0;
                $order_total_number1 = 0;
                $order_effective_number1 = 0;
                $order_cancel_number1 = 0;
                $product_cost1 = 0;
                $running_money_total1 = 0;
                $prescription1 = 0;
                $profit1 = 0;
                $operate_service1 = 0;

                $sales_volume2 = 0;
                $order_receipts2 = 0;
                $order_total_number2 = 0;
                $order_effective_number2 = 0;
                $order_cancel_number2 = 0;
                $product_cost2 = 0;
                $running_money_total2 = 0;
                $prescription2 = 0;
                $profit2 = 0;
                $operate_service2 = 0;
                foreach ($item as $v) {
                    $order_total_number++;
                    if ($v->status <= 18) {
                        $order_effective_number++;
                        $running_money = 0;
                        if (isset($v->running->money) && $v->running->money > 0) {
                            if ($v->running->status === 70) {
                                $running_money = $v->running->money * 100;
                                if ($v->running->shipper_type_ss || $v->running->shipper_type_sf || $v->running->shipper_type_dd || $v->running->shipper_type_zb) {
                                    // 自有运力
                                    $running_money += 10;
                                }
                            }
                        } else {
                            // $running_money = $v->logistics_fee * 100;
                        }
                        $sales_volume += $v->original_price * 100;
                        $order_receipts += ($v->poi_receive + $v->refund_settle_amount) * 100;
                        $product_cost += $v->vip_cost * 100;
                        $running_money_total += $running_money;
                        $prescription += $v->prescription_fee * 100;
                        $profit += $v->poi_receive* 100 - $running_money - $v->vip_cost* 100 - $v->prescription_fee* 100 - $v->operate_service_fee * 100 + $v->refund_operate_service_fee * 100 + $v->refund_settle_amount * 100;
                        $operate_service += ($v->operate_service_fee + $v->refund_operate_service_fee) * 100;
                        if ($v->platform === 1) {
                            $order_total_number1++;
                            $order_effective_number1++;
                            $sales_volume1 += $v->original_price * 100;
                            $order_receipts1 += ($v->poi_receive + $v->refund_settle_amount) * 100;
                            $product_cost1 += $v->vip_cost * 100;
                            $running_money_total1 += $running_money;
                            $prescription1 += $v->prescription_fee * 100;
                            $profit1 += $v->poi_receive* 100 - $running_money - $v->vip_cost* 100 - $v->prescription_fee* 100 - $v->operate_service_fee * 100 + $v->refund_operate_service_fee * 100 + $v->refund_settle_amount * 100;
                            $operate_service1 += ($v->operate_service_fee + $v->refund_operate_service_fee) * 100;
                        } elseif ($v->platform === 2) {
                            $order_total_number2++;
                            $order_effective_number2++;
                            $sales_volume2 += $v->original_price * 100;
                            $order_receipts2 += ($v->poi_receive + $v->refund_settle_amount) * 100;
                            $product_cost2 += $v->vip_cost * 100;
                            $running_money_total2 += $running_money;
                            $prescription2 += $v->prescription_fee * 100;
                            $profit2 += $v->poi_receive* 100 - $running_money - $v->vip_cost* 100 - $v->prescription_fee* 100 - $v->operate_service_fee * 100 + $v->refund_operate_service_fee * 100 + $v->refund_settle_amount * 100;
                            $operate_service2 += ($v->operate_service_fee + $v->refund_operate_service_fee) * 100;
                        }
                    } else {
                        $order_cancel_number++;
                        if ($v->platform === 1) {
                            $order_total_number1++;
                            $order_cancel_number1++;
                        } elseif ($v->platform === 2) {
                            $order_total_number2++;
                            $order_cancel_number2++;
                        }
                    }
                }
                $order_average = (float) sprintf("%.2f", $sales_volume / $order_total_number / 100);
                WmAnalysis::create([
                    'shop_id' => $shop_id,
                    'platform' => 0,
                    'sales_volume' => $sales_volume / 100,
                    'order_receipts' => $order_receipts / 100,
                    'order_total_number' => $order_total_number,
                    'order_effective_number' => $order_effective_number,
                    'order_cancel_number' => $order_cancel_number,
                    'product_cost' => $product_cost / 100,
                    'order_average' => $order_average,
                    'running_money' => $running_money_total / 100,
                    'prescription' => $prescription / 100,
                    'profit' => $profit / 100,
                    'operate_service' => $operate_service / 100,
                    'date' => $date,
                ]);
                if ($order_total_number1 > 0) {
                    $order_average1 = (float) sprintf("%.2f", $sales_volume1 / $order_total_number1 / 100);
                    WmAnalysis::create([
                        'shop_id' => $shop_id,
                        'platform' => 1,
                        'sales_volume' => $sales_volume1 / 100,
                        'order_receipts' => $order_receipts1 / 100,
                        'order_total_number' => $order_total_number1,
                        'order_effective_number' => $order_effective_number1,
                        'order_cancel_number' => $order_cancel_number1,
                        'product_cost' => $product_cost1 / 100,
                        'order_average' => $order_average1,
                        'running_money' => $running_money_total1 / 100,
                        'prescription' => $prescription1 / 100,
                        'profit' => $profit1 / 100,
                        'operate_service' => $operate_service1 / 100,
                        'date' => $date,
                    ]);
                }
                if ($order_total_number2 > 0) {
                    $order_average2 = (float) sprintf("%.2f", $sales_volume2 / $order_total_number2 / 100);
                    WmAnalysis::create([
                        'shop_id' => $shop_id,
                        'platform' => 2,
                        'sales_volume' => $sales_volume2 / 100,
                        'order_receipts' => $order_receipts2 / 100,
                        'order_total_number' => $order_total_number2,
                        'order_effective_number' => $order_effective_number2,
                        'order_cancel_number' => $order_cancel_number2,
                        'product_cost' => $product_cost2 / 100,
                        'order_average' => $order_average2,
                        'running_money' => $running_money_total2 / 100,
                        'prescription' => $prescription2 / 100,
                        'profit' => $profit2 / 100,
                        'operate_service' => $operate_service2 / 100,
                        'date' => $date,
                    ]);
                }
            }
        }
    }
}
