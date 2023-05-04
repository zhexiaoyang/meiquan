<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineDepot extends Model
{
    protected $table = 'wm_depot_medicines';
    protected $fillable = ['name','category','upc','brand','spec','price','guidance_price','down_price'];

    public function category()
    {
        return $this->belongsToMany(MedicineDepotCategory::class, "wm_depot_medicine_category", "medicine_id", "category_id");
    }
}
