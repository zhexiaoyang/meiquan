<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpressOrderLog extends Model
{
    protected $fillable = ['order_id','name','phone','status'];
}
