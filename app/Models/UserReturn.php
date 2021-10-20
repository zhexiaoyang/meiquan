<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReturn extends Model
{
    protected $fillable = ['user_id','running_type','running_value1','running_value2','shop_type','shop_value1','shop_value2'];
}
