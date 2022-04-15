<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipProductPriceLog extends Model
{
    protected $fillable = ['user_id','before','after'];
}
