<?php

namespace App\Support;

use App\Http\Controllers\Api\Concerns\RespondsWithEnvelope;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    use RespondsWithEnvelope {
        fail as protected buildError;
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return (new self())->buildError($message, $status, $errors);
    }
}
