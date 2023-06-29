<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineCategory extends Model
{
    protected $table = 'wm_medicine_categories';
    protected $fillable = ['id','shop_id','pid','name','sort','mt_id','ele_id'];

    public function products()
    {
        return $this->belongsToMany(MedicineDepot::class, "wm_medicine_category", "category_id", "medicine_id");
    }
}
