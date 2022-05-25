<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopShipper extends Model
{
    protected $fillable = ['user_id','shop_id','platform','three_id','access_token','refresh_token','expires_in','token_time'];
}
