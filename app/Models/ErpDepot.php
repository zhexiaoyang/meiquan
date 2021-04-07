<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpDepot extends Model
{
    protected $fillable = ["name", "upc", "c1", "c2", "first_code", "second_code"];
}
