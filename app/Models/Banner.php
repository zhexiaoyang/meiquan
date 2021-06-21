<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ["title", "image", "sort", "status"];

    protected $casts = [
        'status' => 'boolean',
    ];
}
