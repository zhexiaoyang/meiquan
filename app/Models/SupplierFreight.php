<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierFreight extends Model
{
    protected $fillable = ['user_id','first_weight','continuation_weight','city_code'];

    public function city()
    {
        return $this->belongsTo(AddressCity::class, 'city_code', 'id');
    }
}
