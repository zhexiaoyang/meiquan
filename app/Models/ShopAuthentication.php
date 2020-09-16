<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAuthentication extends Model
{
    protected $fillable = ['shop_id', 'yyzz', 'sfz', 'xkz', 'wts', 'examine_user_id', 'examine_at'];
}
