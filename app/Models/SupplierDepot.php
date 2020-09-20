<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDepot extends Model
{

    protected $fillable = ["name","spec","unit","is_otc","description","upc","approval","cover","category_id","price","status","images"];

    public function category()
    {
        return $this->belongsTo(SupplierCategory::class, "category_id", "id");
    }
}
