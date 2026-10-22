<?php

namespace App\Exceptions;

use RuntimeException;

class FacebookAuthException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 400)
    {
        parent::__construct($errorCode);
    }
}
