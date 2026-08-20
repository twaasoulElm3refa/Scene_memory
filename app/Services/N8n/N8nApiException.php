<?php

namespace App\Services\N8n;

use RuntimeException;

class N8nApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
