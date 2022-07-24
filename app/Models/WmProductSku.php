<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmProductSku extends Model
{
    public $timestamps = false;

    // protected $casts = [
    //     'available_times' => 'json',
    // ];

    protected $fillable = ['shop_id','app_poi_code','app_food_code','product_id','box_num','box_price','ladder_box_num',
        'ladder_box_price','location_code','min_order_count','price','cost','sku_id','spec','unit','upc','weight_unit',
        'isSellFlag','weight_for_unit','stock','limit_open_sync_stock_now','available_times'];

    public function product()
    {
        return $this->belongsTo(WmProduct::class, 'product_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    // protected static function boot()
    // {
    //     parent::boot();
    //     // 监听模型创建事件，在写入数据库之前触发
    //     static::created(function ($model) {
    //         if (!$model->sku_id) {
    //             $model->sku_id = 'meiquan_sku_id_' . $model->id;
    //             $model->save();
    //         }
    //     });
    // }
}
