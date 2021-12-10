<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

class Controller extends BaseController
{
    use ApiResponse;

    public $prefix = '';

    public function log($message = '', $data = [])
    {
        if ($this->prefix) {
            $message = $this->prefix . '|' . $message;
        }

        Log::info($message, $data);
    }
}
