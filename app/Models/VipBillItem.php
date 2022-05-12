<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipBillItem extends Model
{
    protected $fillable = ['order_id','order_no','platform','app_poi_code','wm_shop_name','day_seq','trade_type',
        'status','vip_settlement','vip_cost','vip_permission','vip_total','vip_commission_company',
        'vip_commission_manager','vip_commission_operate','vip_commission_internal','vip_commission_business',
        'vip_company','vip_operate','vip_city','vip_internal','vip_business','bill_date','order_at','finish_at',
        'refund_at'];

    public $trade_type = [
        1 => '美团外卖订单',
        2 => '美团订单退款',
        3 => '美团订单部分退款',
        11 => '饿了么外卖订单',
        12 => '饿了么订单退款',
        13 => '饿了么订单部分退款',
        101 => '跑腿订单扣款',
        102 => '跑腿订单取消扣款',
    ];
}
