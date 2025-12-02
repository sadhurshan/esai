<?php

namespace App\Exceptions;

use RuntimeException;

class RfqResponseWindowException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $errors
     */
    public function __construct(string $message, private readonly int $status = 409, private readonly ?array $errors = null)
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }
}
