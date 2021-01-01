<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MkShop extends Model
{
    protected $fillable = ["app_poi_code","name","address","longitude","latitude","pic_url","pic_url_large"];
}
