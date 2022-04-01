<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipBill extends Model
{
    protected $fillable = ['shop_id','mt_id','ele','platform','poi_receive','cost','running','prescription','total',
        'company','date','shop_name'];
}
