<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineSelectShop extends Model
{
    protected $table = 'wm_medicine_select_shops';
    protected $fillable = ['user_id', 'shop_id'];
    public $timestamps = false;

}
