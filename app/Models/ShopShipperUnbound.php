<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopShipperUnbound extends Model
{
    protected $fillable = ['shop_id','user_id','platform','three_id'];
}
