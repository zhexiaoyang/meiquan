<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderRefund extends Model
{
    protected $fillable = ['order_id','refund_id','refund_type','money','reason','ctime'];
}
