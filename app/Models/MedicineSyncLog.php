<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineSyncLog extends Model
{
    protected $table = 'wm_medicine_sync_logs';

    protected $fillable = ['shop_id', 'platform', 'total', 'success', 'fail', 'error', 'mt_success', 'mt_fail', 'ele_success', 'ele_fail', 'status', 'log_id'];
}
