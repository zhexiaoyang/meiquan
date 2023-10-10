<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailSelectShop extends Model
{
    protected $table = 'wm_retail_select_shops';
    protected $fillable = ['user_id', 'shop_id'];
    public $timestamps = false;
}
