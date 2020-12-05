<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpAccessKey extends Model
{
    public function shops()
    {
        return $this->hasMany(ErpAccessShop::class, 'access_id');
    }
}
