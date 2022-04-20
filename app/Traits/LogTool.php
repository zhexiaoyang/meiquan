<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LogTool
{
    public $log_name = '';
    public $prefix = '';

    // public function log($name, $text, $data = [])
    // {
    //     Log::info("[$this->log_name|$name]-$text", $data);
    // }

    public function log_info($message = '', $data = [])
    {
        if ($this->prefix) {
            $message = $this->prefix . '|' . $message;
        }

        Log::info($message, $data);
    }

    public function log_error($message = '', $data = [])
    {
        if ($this->prefix) {
            $message = $this->prefix . '|' . $message;
        }

        Log::error($message, $data);
    }
}
