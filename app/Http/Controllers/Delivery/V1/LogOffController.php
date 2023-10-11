<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\LogOff;
use Illuminate\Http\Request;

class LogOffController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if (LogOff::where('user_id', $user->id)->first()) {
            return $this->message('该账号正在注销中');
        }

        LogOff::create(['user_id' => $user->id]);

        return $this->message('申请注销成功，注销时长1~15日');
    }
}
