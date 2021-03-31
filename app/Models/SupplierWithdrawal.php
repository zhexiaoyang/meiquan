<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierWithdrawal extends Model
{
    protected $fillable = ["user_id","money","description","status"];

    public function supplier()
    {
        return $this->belongsTo(SupplierUser::class, 'user_id');
    }
}
