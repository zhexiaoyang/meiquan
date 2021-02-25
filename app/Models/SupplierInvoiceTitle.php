<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInvoiceTitle extends Model
{
    protected $fillable = ["user_id","title","enterprise","type","number","bank","no","address","phone","receiver_name",
        "receiver_address","receiver_phone"];
}
