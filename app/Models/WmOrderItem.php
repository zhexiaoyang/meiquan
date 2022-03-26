<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderItem extends Model
{
    protected $fillable = ["order_id","app_food_code","box_num","box_price","food_name","unit","upc","quantity",
        "price","spec","sku_id","food_discount","food_property","food_share_fee","cart_id","mt_tag_id","mt_spu_id",
        "mt_sku_id","vip_cost","refund_quantity"];

    public $timestamps = false;
}
