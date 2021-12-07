<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaoziController extends Controller
{
    public function order(Request $request)
    {
        $this->log("桃子医院线下处方回调|全部参数：", $request->all());

        return $this->status(null, 'success', 0);
    }
}
