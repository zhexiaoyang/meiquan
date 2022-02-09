<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSetting extends Model
{
    protected $fillable = ["shop_id","delay_send","delay_reset","type","meituan","fengniao","shansong","shunfeng",
        "dada","uu","warehouse","warehouse_time"];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'warehouse', 'id');
    }
}
