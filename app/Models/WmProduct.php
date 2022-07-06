<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmProduct extends Model
{
    protected $fillable = ['shop_id','app_food_code','name','description','standard_upc','skus','price','unit','cost_price',
        'box_num','min_order_count','box_price','category_code','is_sold_out','picture','sequence','tag_id','sale_type',
        'picture_contents','is_specialty','video_id','common_attr_value','is_show_upc_pic_contents','limit_sale_info',
        'category_name','stock'];

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::created(function ($model) {
            if (!$model->app_food_code) {
                $model->app_food_code = 'meiquan_food_id_' . $model->id;
                $skus = json_decode($model->skus, true);
                if (!empty($skus)) {
                    foreach ($skus as $k => $v) {
                        if (!$v['sku_id']) {
                            $skus[$k]['sku_id'] = $model->app_food_code;
                            if ($k) {
                                $skus[$k]['sku_id'] = $model->app_food_code . $k;
                            }
                        }
                    }
                }
                $model->skus = json_encode($skus, JSON_UNESCAPED_UNICODE);
                $model->save();
            }
        });
    }
}
