<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineShopLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['shop_id', 'online_shop_id', 'status', 'shop_time', 'date'];
}
