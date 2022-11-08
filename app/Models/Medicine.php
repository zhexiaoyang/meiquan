<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $table = 'wm_medicines';

    protected $fillable = ['shop_id','depot_id','name','category','upc','brand','spec','price','guidance_price','depot_id'];



    protected static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            if ($model->depot_id != 0) {
                $category_ids = \DB::table('wm_depot_medicine_category')->where('medicine_id', $model->depot_id)->get()->pluck('category_id');
                if (!empty($category_ids)) {
                    $categories = MedicineDepotCategory::query()->whereIn('id', $category_ids)->get();
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            if (!$c = MedicineCategory::query()->where('shop_id', $model->shop_id)->where('name', $category->name)->first()) {
                                $pid = 0;
                                if ($category->pid != 0) {
                                    if ($category_parent = MedicineDepotCategory::query()->find($category->pid)) {
                                        $w_c_p = MedicineCategory::firstOrCreate([
                                            'shop_id' => $model->shop_id,
                                            'pid' => 0,
                                            'name' => $category_parent->name,
                                            'sort' => $category_parent->sort,
                                        ]);
                                        $pid = $w_c_p->id;
                                    }
                                }
                                $c = MedicineCategory::create([
                                    'shop_id' => $model->shop_id,
                                    'pid' => $pid,
                                    'name' => $category->name,
                                    'sort' => $category->sort,
                                ]);
                            }
                            \DB::table('wm_medicine_category')->insert(['medicine_id' => $model->id, 'category_id' => $c->id]);
                        }
                    }
                }
            } else {
                if (!$c = MedicineCategory::query()->where('shop_id', $model->shop_id)->where('name', '暂未分类')->first()) {
                    $c = MedicineCategory::create([
                        'shop_id' => $model->shop_id,
                        'pid' => 0,
                        'name' => '暂未分类',
                        'sort' => 1000,
                    ]);
                }
                \DB::table('wm_medicine_category')->insert(['medicine_id' => $model->id, 'category_id' => $c->id]);
            }
        });
    }

    public function category()
    {
        return $this->belongsToMany(MedicineDepotCategory::class, "wm_medicine_category", "medicine_id", "category_id");
    }
}
