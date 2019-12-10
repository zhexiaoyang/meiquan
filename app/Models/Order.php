<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $casts =[
        'exception_time' => 'datetime'
    ];

    protected $fillable = ['delivery_id','order_id','shop_id','delivery_service_code','receiver_name',
        'receiver_address','receiver_phone','receiver_lng','receiver_lat','coordinate_type','goods_value',
        'goods_height','goods_width','goods_length','goods_weight','goods_pickup_info',
        'goods_delivery_info','expected_pickup_time','expected_delivery_time','order_type','poi_seq',
        'note','cash_on_delivery','cash_on_pickup','invoice_title','mt_peisong_id', 'courier_name',
        'courier_phone', 'cancel_reason_id', 'cancel_reason','status','failed'];

    public function shop() {
        return $this->belongsTo(Shop::class);
    }
}
