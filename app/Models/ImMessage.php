<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImMessage extends Model
{
    protected $fillable = ['app_id','app_poi_code','order_id','msg_id','msg_type','msg_source','msg_content','biz_type',
        'open_user_id','group_id','app_spu_codes','ctime'];
}
