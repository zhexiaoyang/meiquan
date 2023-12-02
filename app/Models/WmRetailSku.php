<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmRetailSku extends Model
{
    protected $fillable = ['retail_id','shop_id','sku_id','name','category','second_category','cover','upc','brand',
        'spec','price','down_price','guidance_price','gpm','down_gpm','stock','sequence','mt_status','online_mt'];

    protected $casts = [
        'price' => 'double',
        'down_price' => 'double',
        'guidance_price' => 'double'
    ];

    public function retail()
    {
        return $this->belongsTo(WmRetail::class, 'retail_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            if ($model->price > 0) {
                $model->gpm = ($model->price - $model->guidance_price) / $model->price * 100;
            }
            if ($model->down_price > 0) {
                $model->down_gpm = ($model->down_price - $model->guidance_price) / $model->down_price * 100;
            }
        });
    }
}
