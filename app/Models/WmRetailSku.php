<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmRetailSku extends Model
{
    protected $fillable = ['retail_id','shop_id','sku_id','name','category','second_category','cover','upc','brand',
        'spec','price','down_price','guidance_price','gpm','down_gpm','stock','sequence','mt_status','online_mt'];
}
