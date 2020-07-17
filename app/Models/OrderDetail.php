<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected $fillable = ['order_id','goods_id','name','upc','quantity','goods_price','total_price','weight',];
}
