<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AiChatException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $errors
     */
    public function __construct(
        string $message,
        private readonly ?array $errors = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function errors(): ?array
    {
        return $this->errors;
    }
}
