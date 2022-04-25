<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmPrescriptionImport extends Model
{
    protected $fillable = ['count', 'success', 'error', 'exists', 'text', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
