<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LogTool
{
    public $log_name = '';

    public function log($name, $text, $data = [])
    {
        Log::info("[$this->log_name|$name]-$text", $data);
    }
}
