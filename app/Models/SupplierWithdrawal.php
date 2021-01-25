<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierWithdrawal extends Model
{
    protected $fillable = ["user_id","money","description","status"];
}
