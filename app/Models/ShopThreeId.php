<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopThreeId extends Model
{
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
