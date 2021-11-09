<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmPrescription extends Model
{
    protected $fillable = ['clientID','clientName','storeID','storeName','outOrderID','outRpId','outDoctorName',
        'patientName','patientSex','rpStatus','orderStatus','reviewStatus','rejectReason','rpCreateTime','status',
        'reason','platform','shop_id'];
}
