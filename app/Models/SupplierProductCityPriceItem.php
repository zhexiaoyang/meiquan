<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProductCityPriceItem extends Model
{
    public function city()
    {
        return $this->belongsTo(AddressCity::class, 'city_code', 'id');
    }
}
