<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmPrescriptionDown extends Model
{
    protected $fillable = ['title', 'url', 'shop_id', 'user_id', 'sdate', 'edate'];
}
