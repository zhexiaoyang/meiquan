<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmProductLogItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['log_id','name','description','type','status'];
}
