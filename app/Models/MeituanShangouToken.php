<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeituanShangouToken extends Model
{
    protected $fillable = ['shop_id','access_token','refresh_token','expires_at','expires_in'];
}
