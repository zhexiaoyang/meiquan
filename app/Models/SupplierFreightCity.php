<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierFreightCity extends Model
{
    protected $fillable = ['user_id','freight_id','first_weight','continuation_weight','weight1','weight2','city_code'];

    public $timestamps = false;

    public function city()
    {
        return $this->belongsTo(AddressCity::class, 'city_code', 'id');
    }
}
