<?php

namespace App\Models;

use App\Exceptions\InternalException;
use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    protected $fillable = ["user_id","depot_id","price","amount","stock","status","number","weight","product_date","detail"];

    public function depot()
    {
        return $this->hasOne(SupplierDepot::class, "id", "depot_id");
    }

    public function user()
    {
        return $this->hasOne(SupplierUser::class, "id", "user_id");
    }

    public function decreaseStock($amount)
    {
        if ($amount < 0) {
            throw new InternalException('减库存不可小于0');
        }

        return $this->where('id', $this->id)->where('stock', '>=', $amount)->decrement('stock', $amount);
    }

    public function addStock($amount)
    {
        if ($amount < 0) {
            throw new InternalException('加库存不可小于0');
        }
        $this->increment('stock', $amount);
    }
}
