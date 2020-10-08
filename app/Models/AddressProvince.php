<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressProvince extends Model
{
    public function children()
    {
        return $this->hasMany(AddressCity::class, 'province_id', 'id');
    }
}
