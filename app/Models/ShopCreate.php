<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCreate extends Model
{
    protected $fillable = ['user_id','shop_name','mt_shop_id','category','second_category','yyzz','yyzz_img',
        'yyzz_name','sqwts','contact_name','contact_phone','address','city','citycode','district','province','shop_lng',
        'shop_lat','step','status','apply_at','adopt_at'];
}
