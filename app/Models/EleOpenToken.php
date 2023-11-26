<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EleOpenToken extends Model
{
    protected $fillable = ['shop_id','ele_shop_id','access_token','refresh_token','expires_in','expires_at'];
}
