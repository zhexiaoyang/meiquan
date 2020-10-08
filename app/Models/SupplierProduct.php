<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    protected $fillable = ["user_id","depot_id","price","amount","stock","status","number","number","product_date"];

    public function depot()
    {
        return $this->hasOne(SupplierDepot::class, "id", "depot_id");
    }

    public function user()
    {
        return $this->hasOne(SupplierUser::class, "id", "user_id");
    }
}
