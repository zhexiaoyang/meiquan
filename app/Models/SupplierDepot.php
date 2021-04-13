<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDepot extends Model
{

    protected $fillable = ["name","spec","unit","is_otc","description","upc","approval","cover","category_id","price",
        "user_id","status","images","term_of_validity","first_category","second_category"];

    public function category()
    {
        return $this->belongsTo(SupplierCategory::class, "category_id", "id");
    }

    public function first()
    {
        return $this->belongsTo(Category::class, 'first_category', 'id');
    }

    public function second()
    {
        return $this->belongsTo(Category::class, 'second_category', 'id');
    }
}
