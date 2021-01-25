<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierUserBalance extends Model
{
    protected $fillable = ["user_id","type","money","before_money","after_money","description","tid"];
}
