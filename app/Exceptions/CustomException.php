<?php

namespace App\Exceptions;

use Exception;

class CustomException extends Exception
{
    public function __construct($message, $code = 555, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
