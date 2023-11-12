<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopRestLog extends Model
{
    protected $fillable = ['shop_id','mt_shop_id','shop_name','wm_shop_name','type','status','error'];
}
