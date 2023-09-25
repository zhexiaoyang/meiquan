<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImMessageItem extends Model
{
    protected $fillable = ['message_id','msg_id','msg_type','msg_source','msg_content','open_user_id','group_id',
        'app_spu_codes','ctime','is_read','from'];
}
