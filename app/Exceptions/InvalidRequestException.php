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
        return response()->json(['msg' => $this->message], $this->code);
    }
}
