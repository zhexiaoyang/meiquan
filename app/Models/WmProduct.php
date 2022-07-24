<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmProduct extends Model
{
    protected $fillable = ['shop_id','app_food_code','name','description','standard_upc','price','unit','cost_price',
        'box_num','min_order_count','box_price','category_code','is_sold_out','picture','sequence','tag_id','sale_type',
        'picture_contents','is_specialty','video_id','common_attr_value','is_show_upc_pic_contents','limit_sale_info',
        'category_name','stock','app_poi_code'];

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            if (!$model->app_food_code) {
                $model->app_food_code = 'mqcode_' . mt_rand(1000, 9999) . time();
            }
        });
    }

    public function skus()
    {
        return $this->hasMany(WmProductSku::class, 'product_id', 'id');
    }
}
