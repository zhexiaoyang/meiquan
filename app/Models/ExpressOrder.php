<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpressOrder extends Model
{
    protected $fillable = ['order_id','shop_id','receive_name','receive_phone','province','city','area','address',
        'courier_name','courier_mobile','weight','freight','status','platform','task_id','user_id'];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
