<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrderReceive extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id','comment','fee_desc','money','type'];
}
