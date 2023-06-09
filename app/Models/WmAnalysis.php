<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmAnalysis extends Model
{
    protected $fillable = ['shop_id','platform','sales_volume','order_receipts','order_total_number','product_cost','operate_service',
        'order_average','running_money','prescription','profit','date','order_effective_number','order_cancel_number'];

    public function shop() {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
