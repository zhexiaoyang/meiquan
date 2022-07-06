<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmCategory extends Model
{
    protected $fillable = ['pid','shop_id','code','name','sequence','top_flag','weeks_time','period','smart_switch'];

    public function products()
    {
        return $this->hasMany(WmProduct::class, 'category_code', 'code');
    }

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::created(function ($model) {
            if (!$model->code) {
                $model->code = 'meiquan_food_cat_id_' . $model->id;
                $model->save();
            }
        });
    }
}
