<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOperateBalance extends Model
{
    protected $fillable = ["user_id","money","type","before_money","after_money","description","tid","order_at","shop_id","type2","order_id",];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
