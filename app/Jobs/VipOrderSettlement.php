<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\Shop;
use App\Models\VipBillItem;
use App\Models\WmOrder;
use App\Traits\LogTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VipOrderSettlement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogTool;

    protected $order;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(WmOrder $order)
    {
        $this->order = $order;
        $this->prefix = 'VIP订单计算佣金|'.$order->order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $order = $this->order;

        if (!$shop = Shop::find($order->shop_id)) {
            $this->log_info('错误|门店不存在');
            return;
        }

        $platform = $order->platform;
        // 处方审方扣费
        $prescription = $order->prescription_fee;
        // 总利润
        $total = $order->poi_receive - $order->vip_cost - $prescription;
        // VIP门店各方利润百分比
        $commission = $shop->vip_commission;
        $commission_manager = $shop->vip_commission_manager;
        $commission_operate = $shop->vip_commission_operate;
        $commission_internal = $shop->vip_commission_internal;
        $business = 100 - $commission - $commission_manager - $commission_operate - $commission_internal;
        // $this->log_info("百分比|公司:{$commission}|运营经理:{$commission_operate}|城市经理:{$commission_manager}|内勤:{$commission_internal}|商家:{$business}|");
        if ($commission + $commission_manager + $commission_operate + $commission_internal > 100) {
            $this->ding_error('错误|佣金比例大于100%');
            return;
        }
        $vip_city = sprintf("%.2f",$total * $commission_manager / 100);
        $vip_operate = sprintf("%.2f", $total * $commission_operate / 100);
        $vip_internal = sprintf("%.2f",$total * $commission_internal / 100);
        $vip_business = sprintf("%.2f",$total * $business / 100);
        $vip_company = sprintf("%.2f",$total - $vip_operate - $vip_city - $vip_internal - $vip_business);
        $item = [
            'shop_id' => $order->shop_id,
            'order_id' => $order->id,
            'order_no' => $order->order_id,
            'platform' => $platform,
            'app_poi_code' => $order->app_poi_code,
            'wm_shop_name' => $order->wm_shop_name,
            'day_seq' => $order->day_seq,
            'trade_type' => $platform == 1 ? 1 : 11,
            'status' => $order->status,
            'order_at' => $order->created_at,
            'finish_at' => $order->finish_at,
            'bill_date' => $order->bill_date,
            'vip_settlement' => $order->poi_receive,
            'vip_cost' => $order->vip_cost,
            'vip_permission' => $prescription,
            'vip_total' => $total,
            'vip_commission_company' => $commission,
            'vip_commission_manager' => $commission_manager,
            'vip_commission_operate' => $commission_operate,
            'vip_commission_internal' => $commission_internal,
            'vip_commission_business' => $business,
            'vip_company' => $vip_company,
            'vip_city' => $vip_city,
            'vip_operate' => $vip_operate,
            'vip_internal' => $vip_internal,
            'vip_business' => $vip_business,
        ];
        VipBillItem::create($item);
        \Log::info("VIP订单结算处理，完成订单结算成功");

        // $commission = $shop->vip_commission;
        // $commission_manager = $shop->vip_commission_manager;
        // $commission_operate = $shop->vip_commission_operate;
        // $commission_internal = $shop->vip_commission_internal;
        // $business = 100 - $commission - $commission_manager - $commission_operate - $commission_internal;
        // $this->log_info("百分比|公司:{$commission}|运营经理:{$commission_operate}|城市经理:{$commission_manager}|内勤:{$commission_internal}|商家:{$business}|");
        //
        // if ($commission + $commission_manager + $commission_operate + $commission_internal > 100) {
        //     $this->log_info('错误|佣金比例大于100%');
        //     return;
        // }
        //
        // if ($this->order->status != 18 && $this->order->status != 30) {
        //     $this->log_info('错误|订单状态不正确|状态：' . $this->order->status);
        //     return;
        // }
        //
        // if ($running = Order::where('order_id', $this->order->order_id)->first()) {
        //     $running_money = 0;
        //     if ($running->status == 70) {
        //         $running_money += $running->money;
        //     }
        //     $reduce = OrderDeduction::where('order_id', $this->order->id)->sum('money');
        //     if ($reduce > 0) {
        //         $this->log_info('跑腿扣款大于0|扣款：' . $reduce);
        //     }
        //     $running_money += $reduce;
        //     $this->order->running_fee = $running_money;
        //     $this->order->save();
        // }
        //
        // // 美团结算
        // $poi_receive = $this->order->poi_receive;
        // // 跑腿费
        // $running_fee = $this->order->running_fee;
        // // 处方费
        // $prescription_fee = $this->order->prescription_fee;
        // // 成本费
        // $vip_cost = $this->order->vip_cost;
        // // 退款金额
        // $refund_fee = $this->order->refund_fee;
        // // 总收益
        // $total = (($poi_receive * 100) - ($running_fee * 100) - ($prescription_fee * 100) - ($vip_cost * 100) - ($refund_fee * 100)) / 100;
        //
        // $this->log_info("结算金额|美团结算:{$poi_receive}|跑腿费:{$running_fee}|处方费:{$prescription_fee}|成本费:{$vip_cost}|退款金额:{$refund_fee}|总收益:{$total}|");
        //
        // $vip_operate = sprintf("%.2f", $total * $commission_operate / 100);
        // $vip_city = sprintf("%.2f",$total * $commission_manager / 100);
        // $vip_internal = sprintf("%.2f",$total * $commission_internal / 100);
        // $vip_business = sprintf("%.2f",$total * $business / 100);
        // // $vip_company = $total * $commission / 100;
        // $vip_company = sprintf("%.2f",$total - $vip_operate - $vip_city - $vip_internal - $vip_business);
        //
        // $this->log_info("计算佣金|公司:{$vip_company}|运营经理:{$vip_operate}|城市经理:{$vip_city}|内勤:{$vip_internal}|商家:{$vip_business}|");
        //
        // $this->order->vip_total = $total;
        // $this->order->vip_company = $vip_company;
        // $this->order->vip_operate = $vip_operate;
        // $this->order->vip_city = $vip_city;
        // $this->order->vip_internal = $vip_internal;
        // $this->order->vip_business = $vip_business;
        //
        // $this->order->save();
    }
}
