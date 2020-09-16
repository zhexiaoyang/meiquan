<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierOrderItem extends Model
{
    protected $fillable = ['amount', 'price', 'rating', 'review', 'reviewed_at','name','cover','spec','unit'];
    protected $dates = ['reviewed_at'];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function order()
    {
        return $this->belongsTo(SupplierOrder::class);
    }
}
