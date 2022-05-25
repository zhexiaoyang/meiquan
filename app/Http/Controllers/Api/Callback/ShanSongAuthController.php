<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShanSongAuthController extends Controller
{
    public function auth(Request $request)
    {
        return $this->success($request->all());
    }
}
