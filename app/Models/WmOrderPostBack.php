<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderPostBack extends Model
{
    protected $fillable = ['order_id','order_no','status','ps','three_order_no','courier_name','courier_phone',
        'longitude','latitude',];
}
