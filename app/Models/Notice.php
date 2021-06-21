<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable = ["notice", "sort", "status"];

    protected $casts = [
        'status' => 'boolean',
    ];
}
