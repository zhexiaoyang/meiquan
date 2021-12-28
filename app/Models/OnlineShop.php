<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineShop extends Model
{
    use Traits\OnlineShopHelper;

    protected $fillable = ["user_id","name","category","category_second","shop_lng","shop_lat","address","phone","contact_name",
        "contact_phone","mobile","business_hours","account_no","bank_user","bank_name","manager_name","manager_phone","manager_id",
        "city","citycode","remark","chang","sqwts","yyzz","ypjy","spjy","ylqx","sfz","wts","front","environmental",
        "yyzz_start_time","yyzz_end_time","ypjy_start_time","ypjy_end_time","spjy_start_time","spjy_end_time",
        "ylqx_start_time","ylqx_end_time","status","reason","shop_id","sfzbm","sfzsc","sfzscbm","is_meituan","is_ele","is_jddj",
        "is_btoc"];

    public function reasons()
    {
        return $this->hasMany(OnlineShopReason::class, 'oid', 'id');
    }

    public function contract()
    {
        return $this->hasMany(ContractOrder::class, 'online_shop_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
