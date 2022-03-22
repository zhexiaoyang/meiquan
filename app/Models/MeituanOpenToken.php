<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeituanOpenToken extends Model
{
    protected $fillable = ['shop_id', 'mt_shop_id', 'token'];
}
