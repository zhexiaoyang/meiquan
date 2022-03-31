<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipProductException extends Model
{
    protected $fillable = ['product_id','shop_id','shop_name','name','spec','upc','mt_id','price','cost','platform',
        'error','error_type','message','status'];
}
