<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCart extends Model
{
    protected $fillable = ["amount","user_id","product_id"];

    public function product()
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
