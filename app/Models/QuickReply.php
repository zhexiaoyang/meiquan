<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    protected $fillable = ['user_id', 'text'];
}
