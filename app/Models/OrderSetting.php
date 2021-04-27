<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSetting extends Model
{
    protected $fillable = ["user_id","delay_send","delay_reset","type","meituan","fengniao","shansong","shunfeng","dada"];
}
