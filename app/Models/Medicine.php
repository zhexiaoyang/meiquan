<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Medicine extends Model
{
    protected $table = 'wm_medicines';

    protected $fillable = ['shop_id','depot_id','name','sequence','category','upc','brand','spec','stock','price',
        'down_price','store_id','guidance_price','cover','gpm','down_gpm','online_mt','online_ele','mt_status',
        'ele_sku_id','ele_status'];

    protected $casts = [
        'price' => 'float',
        'guidance_price' => 'float',
    ];



    protected static function boot()
    {
        parent::boot();
        // static::created(function ($model) {
        //     if ($model->depot_id != 0) {
        //         // 品库中存在的商品，按照品库分类创建分类
        //         // 查找品库中该分类的分类ID
        //         $category_ids = \DB::table('wm_depot_medicine_category')->where('medicine_id', $model->depot_id)->get()->pluck('category_id');
        //         if (!empty($category_ids)) {
        //             // 根据查找的分类ID，查找该商品所有分类
        //             $categories = MedicineDepotCategory::whereIn('id', $category_ids)->get();
        //             if (!empty($categories)) {
        //                 foreach ($categories as $category) {
        //                     // \Log::info('--------------------s | ' . $category->name);
        //                     if (!$c = MedicineCategory::where('shop_id', $model->shop_id)->where('name', $category->name)->first()) {
        //                         // 如果该分类没有创建过分类，执行创建分类
        //                         // 默认是一级分类
        //                         // \Log::info("分类名称：{$category->name},上级分类ID：{$category->pid},");
        //                         $pid = 0;
        //                         if ($category->pid != 0) {
        //                             // \Log::info('不是一级分类');
        //                             if ($category_parent = MedicineDepotCategory::find($category->pid)) {
        //                                 // \Log::info('找到上级分类');
        //                                 // 如果不是一级分类，创建一级分类
        //                                 if (!$_c = MedicineCategory::where(['shop_id' => $model->shop_id, 'name' => $category_parent->name])->first()) {
        //                                     // \Log::info('上级分类没有创建');
        //                                     // 查找父级分类，并创建分类
        //                                     try {
        //
        //                                         $w_c_p = MedicineCategory::create([
        //                                             'shop_id' => $model->shop_id,
        //                                             'pid' => 0,
        //                                             'name' => $category_parent->name,
        //                                             'sort' => $category_parent->sort,
        //                                         ]);
        //                                         $pid = $w_c_p->id;
        //                                     } catch (QueryException $exception) {
        //                                         \Log::info("导入商品创建分类一级报错");
        //                                         if ($w_c_p = MedicineCategory::where(['shop_id' => $model->shop_id, 'name' => $category_parent->name])->first()) {
        //                                             $pid = $w_c_p->id;
        //                                         } else {
        //                                             \Log::info("导入商品创建分类一级报错-重新查找分类-不存在|商品ID：" . $model->id);
        //                                         }
        //                                     }
        //                                     // \Log::info('创建上级分类返回', [$w_c_p]);
        //                                 } else {
        //                                     $pid = $_c->id;
        //                                 }
        //                             }
        //                         }
        //                         // \Log::info("上级分类ID：{$pid},");
        //                         if (!$c = MedicineCategory::where(['shop_id' => $model->shop_id, 'name' => $category->name])->first()) {
        //                             try {
        //                                 $c = MedicineCategory::create([
        //                                     'shop_id' => $model->shop_id,
        //                                     'pid' => $pid,
        //                                     'name' => $category->name,
        //                                     'sort' => $category->sort,
        //                                 ]);
        //                             } catch (QueryException $exception) {
        //                                 \Log::info("导入商品创建分类报错|商品ID：{$model->id}|分类名称：{$category->name}");
        //                             }
        //                         }
        //                     }
        //                     // \Log::info('--------------------e | ' . $category->name);
        //                     \DB::table('wm_medicine_category')->insert(['medicine_id' => $model->id, 'category_id' => $c->id]);
        //                 }
        //             }
        //         }
        //     } else {
        //         if (!$c = MedicineCategory::where('shop_id', $model->shop_id)->where('name', '暂未分类')->first()) {
        //             $c = MedicineCategory::create([
        //                 'shop_id' => $model->shop_id,
        //                 'pid' => 0,
        //                 'name' => '暂未分类',
        //                 'sort' => 1000,
        //             ]);
        //         }
        //         \DB::table('wm_medicine_category')->insert(['medicine_id' => $model->id, 'category_id' => $c->id]);
        //     }
        // });
        static::saving(function ($model) {
            if ($model->price > 0) {
                $model->gpm = ($model->price - $model->guidance_price) / $model->price * 100;
            }
            if ($model->down_price > 0) {
                $model->down_gpm = ($model->down_price - $model->guidance_price) / $model->down_price * 100;
            }
            // \Log::info('$model', [$model]);
            // \Log::info('$model2', [$model2]);
        });
    }

    public function categories()
    {
        return $this->belongsToMany(MedicineCategory::class, "wm_medicine_category", "medicine_id", "category_id");
    }
}
