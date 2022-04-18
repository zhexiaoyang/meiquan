<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipProductException extends Model
{
    /**
     * error_type
     * 1. 成本价为0
     * 2. 成本价大于等于销售价
     * 3. ERP中商品未找到
     */

    protected $fillable = ['product_id','shop_id','shop_name','name','spec','upc','mt_id','price','cost','platform',
        'error','error_type','message','status'];
}
