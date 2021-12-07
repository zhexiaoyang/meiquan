<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

class Controller extends BaseController
{
    use ApiResponse;

    public function log($message = '', $data = [])
    {
        Log::info($message, $data);
    }
}
