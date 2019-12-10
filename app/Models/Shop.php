<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ['shop_id','shop_name','category','second_category','contact_name','contact_phone',
        'shop_address','shop_lng','shop_lat','coordinate_type','delivery_service_codes','business_hours','status'];

    protected $casts = [
        'business_hours' => 'json',
    ];

    public function orders() {
        return $this->hasMany(Order::class);
    }
}
