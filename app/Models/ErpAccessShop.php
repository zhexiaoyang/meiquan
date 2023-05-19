<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpAccessShop extends Model
{
    protected $fillable = ["access_id", "shop_id", "shop_name", "mq_shop_id", "mt_shop_id", "type", "sync_status"];

    public function shop()
    {
        return $this->hasOne(Shop::class, 'id', 'mq_shop_id');
    }
}
