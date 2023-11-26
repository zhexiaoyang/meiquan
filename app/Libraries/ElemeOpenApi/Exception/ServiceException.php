<?php

namespace App\Libraries\ElemeOpenApi\Exception;

use LogicException;

class ServiceException extends LogicException
{
    public function __construct($message, int $code = 400)
    {
        parent::__construct($message, $code);
        if (is_null($message)) {
            return;
        }

        $this->message = $message;
    }
}
