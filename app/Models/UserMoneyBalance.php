<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMoneyBalance extends Model
{
    protected $fillable = ["user_id","money","type","before_money","after_money","description","tid"];
}
