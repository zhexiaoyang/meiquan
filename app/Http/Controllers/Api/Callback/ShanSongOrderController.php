<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShanSongOrderController extends Controller
{
    public function order(Request $request)
    {
        return $this->success($request->all());
    }
}
