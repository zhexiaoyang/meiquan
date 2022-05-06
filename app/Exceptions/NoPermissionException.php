<?php

namespace App\Exceptions;

use Exception;

class NoPermissionException extends Exception
{

    public function __construct(string $message = "无权限", int $code = 400)
    {
        parent::__construct($message, $code);
    }

    public function render()
    {
        $response = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        return response()->json($response, 200);
    }
}
