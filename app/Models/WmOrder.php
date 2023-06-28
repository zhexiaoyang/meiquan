<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrder extends Model
{
    //
    protected $fillable = [
        'shop_id','order_id','wm_order_id_view','ware_order_id','from_type','platform','channel','way','app_poi_code',
        'wm_shop_name','recipient_name','recipient_address','recipient_address_detail','recipient_phone','latitude',
        'longitude','shipping_fee','total','original_price','goods_price','package_bag_money_yuan','service_fee',
        'logistics_fee','online_payment','poi_receive','rebate_fee','caution','shipper_phone','status','invoice_title',
        'taxpayer_id','ware_status','ware_error','ware_take_code','ctime','utime','delivery_time',
        'estimate_arrival_time','pick_type','day_seq','logistics_code','is_favorites','is_poi_first_order',
        'is_pre_sale_order','is_prescription','send_at','finish_at','shipper_phone','is_vip','running_fee','prescription_fee',
        'cancel_reason','cancel_at','refund_fee','refund_status','vip_cost','user_id','print_number','rp_picture',
        'running_service_type','running_service_fee','operate_service_rate','operate_service_fee',
        'refund_platform_charge_fee','refund_settle_amount','refund_operate_service_fee'
    ];

    public function items()
    {
        return $this->hasMany(WmOrderItem::class, 'order_id');
    }

    public function bill_items()
    {
        return $this->hasMany(VipBillItem::class, 'order_id');
    }

    public function receives()
    {
        return $this->hasMany(WmOrderReceive::class, 'order_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function running()
    {
        return $this->hasOne(Order::class, 'wm_id');
    }
}
