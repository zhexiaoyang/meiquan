<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopThreeId extends Model
{
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function conflict_mt()
    {
        return $this->belongsTo(Shop::class, 'mtwm', 'mtwm');
    }

    public function conflict_ele()
    {
        return $this->belongsTo(Shop::class, 'ele', 'ele');
    }
}
