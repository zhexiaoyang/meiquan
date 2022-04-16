<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderResend extends Model
{
    protected $fillable = ['order_id','delivery_id','user_id',];
}
