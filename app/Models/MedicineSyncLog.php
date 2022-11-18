<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineSyncLog extends Model
{
    protected $table = 'wm_medicine_sync_logs';

    protected $fillable = ['shop_id', 'platform', 'total', 'success', 'fail', 'error', 'status', 'log_id'];
}
