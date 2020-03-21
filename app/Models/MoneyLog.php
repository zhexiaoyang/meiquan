<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyLog extends Model
{
    protected $fillable = ['order_id','amount','status','type'];
}
