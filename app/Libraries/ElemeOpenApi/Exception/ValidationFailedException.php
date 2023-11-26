<?php

namespace App\Libraries\ElemeOpenApi\Exception;

use Illuminate\Support\Facades\Response;

class ValidationFailedException extends ServiceException
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

        return Response::json($response, 200)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }
}
