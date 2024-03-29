<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpressOrder extends Model
{
    protected $fillable = ['order_id','shop_id','receive_name','receive_phone','province','city','area','address',
        'three_order_id','send_name','send_phone','send_address','send_province','send_city','send_area',
        'courier_name','courier_mobile','weight','freight','status','platform','task_id','user_id'];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(ExpressOrderLog::class, 'order_id', 'id');
    }
}
