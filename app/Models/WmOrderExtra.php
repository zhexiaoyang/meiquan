<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderExtra extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id','reduce_fee','poi_charge','mt_charge','remark','type','gift_name','gift_num'];
}
