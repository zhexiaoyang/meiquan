<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPostback extends Model
{
    protected $fillable = ['shop_id', 'success', 'fail', 'rate', 'date'];
}
