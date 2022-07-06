<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmProductLog extends Model
{
    protected $fillable = ['from_shop', 'from_shop_name', 'go_shop', 'go_shop_name', 'user_id', 'success', 'error',
        'total', 'status', 'fail'];

    // public function shop()
    // {
    //     return $this->hasOne(Shop::class, 'id', 'shop_id');
    // }
}
