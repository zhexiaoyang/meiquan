<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = ['version_number','version_code','platform','force','filename','url','channel_id','status'];
}
