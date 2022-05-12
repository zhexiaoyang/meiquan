<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class OrderDeduction extends Model
{
    protected $fillable = ['order_id', 'money', 'ps'];

    protected static function boot()
    {
        static::created(function ($model) {
            if ($order = Order::find($model->order_id)) {
                if ($wm_order = WmOrder::where('is_vip', 1)->where('id', $order->wm_id)->first()) {
                    if ($shop = Shop::find($order->shop_id)) {
                        // VIP门店各方利润百分比
                        $commission = $shop->vip_commission;
                        $commission_manager = $shop->vip_commission_manager;
                        $commission_operate = $shop->vip_commission_operate;
                        $commission_internal = $shop->vip_commission_internal;
                        $business = 100 - $commission - $commission_manager - $commission_operate - $commission_internal;
                        // 跑腿扣款收入（负值）
                        $poi_receive = 0 - $model->money;
                        // 总收入
                        $total = $poi_receive;
                        $vip_city = sprintf("%.2f",$total * $commission_manager / 100);
                        $vip_operate = sprintf("%.2f", $total * $commission_operate / 100);
                        $vip_internal = sprintf("%.2f",$total * $commission_internal / 100);
                        $vip_business = sprintf("%.2f",$total * $business / 100);
                        $vip_company = sprintf("%.2f",$total - $vip_operate - $vip_city - $vip_internal - $vip_business);
                        $item = [
                            'order_id' => $wm_order->id,
                            'order_no' => $wm_order->order_id,
                            'platform' => $wm_order->platform,
                            'app_poi_code' => $wm_order->app_poi_code,
                            'wm_shop_name' => $wm_order->wm_shop_name,
                            'day_seq' => $wm_order->day_seq,
                            'trade_type' => 102,
                            'status' => $wm_order->status,
                            'order_at' => $wm_order->created_at,
                            'finish_at' => $wm_order->finish_at,
                            'bill_date' => $order->over_at,
                            'vip_settlement' => $poi_receive,
                            'vip_cost' => 0,
                            'vip_permission' => 0,
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
                    }
                }
            }
        });
    }
}
