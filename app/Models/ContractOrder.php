<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractOrder extends Model
{
    protected $fillable = ['user_id','shop_id','online_shop_id','order_id','created_at','updated_at'];
}
