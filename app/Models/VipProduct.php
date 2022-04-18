<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipProduct extends Model
{
    protected $fillable = ['shop_id','name','app_medicine_code','upc','medicine_no','spec','price','sequence',
        'category_name','stock','ctime','utime','platform','platform_id','shop_name','cost','updated_at'];

    /**
     * ERP配置
     */
    public function erp() {
        return $this->hasOne(ShopErpSetting::class, "shop_id", "shop_id");
    }
}
