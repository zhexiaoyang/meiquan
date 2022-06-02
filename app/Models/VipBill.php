<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipBill extends Model
{
    protected $fillable = ['shop_id','mt_id','ele','platform','poi_receive','cost','running','prescription','total',
        'company','date','shop_name',
        'vip_settlement','vip_cost','vip_permission','vip_total','vip_company','vip_operate','vip_city','vip_internal','vip_business'
    ];
}
