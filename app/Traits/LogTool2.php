<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LogTool2
{
    public $log_tool2_name = '';
    public $log_tool2_prefix = '';

    public function log_info($message = '', $data = [])
    {
        if ($this->log_tool2_prefix) {
            $message = $this->log_tool2_prefix . '|' . $message;
        }

        Log::info($message, is_array($data) ? $data : [$data]);
    }

    public function log_error($message = '', $data = [])
    {
        if ($this->log_tool2_prefix) {
            $message = $this->log_tool2_prefix . '|' . $message;
        }

        Log::error($message, is_array($data) ? $data : [$data]);
    }
}
