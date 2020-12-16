<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineShop extends Model
{
    protected $fillable = ["user_id","name","category","category_second","shop_lng","shop_lat","address","phone","contact_name",
        "contact_phone","mobile","business_hours","account_no","bank_user","bank_name","manager_name","manager_phone",
        "city","citycode","remark","chang","sqwts","yyzz","ypjy","spjy","ylqx","sfz","wts","front","environmental",
        "yyzz_start_time","yyzz_end_time","ypjy_start_time","ypjy_end_time","spjy_start_time","spjy_end_time",
        "ylqx_start_time","ylqx_end_time","status","reason"];
}
