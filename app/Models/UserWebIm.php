<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWebIm extends Model
{
    protected $fillable = ['user_id','platform','auth','description'];
}
