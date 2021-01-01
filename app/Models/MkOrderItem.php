<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MkOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ["order_id","mt_order_id","app_food_code","food_name","sku_id","upc","quantity","price","box_num",
        "box_price","unit","spec","weight"];
}
