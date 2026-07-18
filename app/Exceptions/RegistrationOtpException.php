<?php

namespace App\Exceptions;

use RuntimeException;

class RegistrationOtpException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 422,
        private readonly ?string $field = null
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errors(): ?array
    {
        if (! $this->field) {
            return null;
        }

        return [
            $this->field => [$this->getMessage()],
        ];
    }
}
