<?php

namespace App\Exceptions;

use Exception;

class InvalidRequestException extends Exception
{

    public function __construct(string $message = "", int $code = 400)
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
