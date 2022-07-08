<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractOrder extends Model
{
    protected $fillable = ['user_id','shop_id','online_shop_id','order_id','contract_id','three_contract_id',
        'three_sign','status',];
}
