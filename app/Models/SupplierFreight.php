<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierFreight extends Model
{
    protected $fillable = ['user_id','first_weight','continuation_weight','weight1','weight2'];

    public function cities()
    {
        return $this->hasMany(SupplierFreightCity::class, 'freight_id');
    }
}
