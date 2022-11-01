<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineDepotCategory extends Model
{
    protected $table = 'wm_depot_medicine_categories';
    protected $fillable = ['id','pid','name','sort'];

    public function products()
    {
        return $this->belongsToMany(MedicineDepot::class, "wm_depot_medicine_category", "category_id", "medicine_id");
    }
}
