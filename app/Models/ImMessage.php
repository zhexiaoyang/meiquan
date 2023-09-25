<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImMessage extends Model
{
    protected $fillable = ['shop_id','user_id','app_id','app_poi_code','order_id','msg_id','msg_content','biz_type',
        'is_read','day_seq','name','title','image','group_id','open_user_id','ctime', 'type', 'is_reply'];
}
