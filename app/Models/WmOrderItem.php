<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderItem extends Model
{
    protected $fillable = ['order_id','app_food_code','food_name','unit','upc','quantity','price','spec','vip_cost'];
}
