<?php

namespace App\Exceptions;

use Exception;

class InternalException extends Exception
{

    protected $msgForUser;

    public function __construct(string $message, string $msgForUser = '系统内部错误', int $code = 500)
    {
        parent::__construct($message, $code);
        $this->msgForUser = $msgForUser;
    }

    public function render()
    {
        return response()->json(['msg' => $this->msgForUser], $this->code);
    }
}
