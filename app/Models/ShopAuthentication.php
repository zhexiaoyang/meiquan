<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAuthentication extends Model
{
    protected $fillable = ["shop_id","yyzz","chang","yyzz_start_time","yyzz_end_time","xkz","ypjy_start_time",
        "ypjy_end_time","spjy","spjy_start_time","spjy_end_time","ylqx","ylqx_start_time","ylqx_end_time",
        "elqx","sfz","wts","examine_user_id","examine_at"];
}
