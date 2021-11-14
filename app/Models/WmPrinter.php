<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmPrinter extends Model
{
    protected $fillable = ['shop_id','platform','key','sn','name','number'];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
