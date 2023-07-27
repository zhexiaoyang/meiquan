<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDeliveryTrack extends Model
{
    protected $fillable = ['delivery_id','order_id','wm_id','status','status_desc','delivery_name','delivery_phone',
        'delivery_lng','delivery_lat','description'];
}
