<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProductCityPrice extends Model
{
    protected $fillable = ['product_id', 'price'];

    public function items()
    {
        return $this->hasMany(SupplierProductCityPriceItem::class, 'price_id');
    }
}
