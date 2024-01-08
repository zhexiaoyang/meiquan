<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderRefund extends Model
{
    protected $fillable = ['user_id','order_id','shop_id','order_no','refund_id','refund_type','money','reason','ctime'];
}
