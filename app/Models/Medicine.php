<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $table = 'wm_medicines';

    protected $fillable = ['shop_id','depot_id','name','category','upc','brand','spec','price','guidance_price'];



    // protected static function boot()
    // {
    //     parent::boot();
    //     static::created(function ($model) {
    //         if ($category = MedicineCategory::where('name', $model->shop_id)->first()) {
    //             $model->distance = getShopDistanceV4($shop, $model->receiver_lng, $model->receiver_lat);
    //         }
    //     });
    // }

    public function category()
    {
        return $this->belongsToMany(MedicineDepotCategory::class, "wm_medicine_category", "medicine_id", "category_id");
    }
}
