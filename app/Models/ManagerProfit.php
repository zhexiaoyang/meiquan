<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagerProfit extends Model
{
    protected $fillable = ['user_id','order_id','order_no','shop_id','shop_name','order_profit','profit','return_type',
        'return_value','description','type','created_at','updated_at'];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
