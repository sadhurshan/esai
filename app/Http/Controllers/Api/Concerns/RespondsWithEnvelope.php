<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithEnvelope
{
    protected function ok(mixed $data = null, ?string $message = null, ?array $meta = null): JsonResponse
    {
        $payload = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    protected function fail(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }
}
