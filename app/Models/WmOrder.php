<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrder extends Model
{
    //
    protected $fillable = ["shop_id","order_id","wm_order_id_view","platform","from_type","app_poi_code","wm_shop_name",
        "recipient_name","recipient_phone","recipient_address","latitude","longitude","shipping_fee","total",
        "original_price","caution","shipper_phone","status","ctime","utime","delivery_time","pick_type","day_seq"];

    public function items()
    {
        return $this->hasMany(WmOrderItem::class, 'order_id');
    }
}
