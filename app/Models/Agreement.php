<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{

    protected $fillable = [
        'cover',
        'title',
        'url',
        'status',
        'date'
    ];
}
