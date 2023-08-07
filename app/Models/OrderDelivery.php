<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDelivery extends Model
{
    protected $fillable = ['shop_id','warehouse_id','order_id','wm_id','order_no','three_order_no','bill_no','platform',
        'type','day_seq','money','original','coupon','insurance','tip','distance','weight','remark','delivery_name',
        'delivery_phone','delivery_lng','delivery_lat','is_payment','is_refund','status','track','send_at','arrival_at',
        'atshop_at','pickup_at','finished_at','cancel_at','paid_at','refund_at',
        'user_id','add_money','addfee'
    ];

    // 足迹记录
    public function tracks()
    {
        return $this->hasMany(OrderDeliveryTrack::class, "delivery_id", "id");
    }
}
