<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmRetail extends Model
{
    protected $fillable = ['shop_id','store_id','name','category','second_category','cover','upc','sequence'];

    public function skus()
    {
        return $this->hasMany(WmRetailSku::class, 'retail_id');
    }
}
