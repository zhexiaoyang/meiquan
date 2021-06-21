<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchKeyIndex extends Model
{
    protected $fillable = ["text", "sort", "status"];

    protected $casts = [
        'status' => 'boolean',
    ];
}
