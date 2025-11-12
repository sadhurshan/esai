<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait RespondsWithPlanUpgrade
{
    protected function upgradeRequiredResponse(?array $errors = null, ?string $message = null, int $status = Response::HTTP_PAYMENT_REQUIRED): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message ?? 'Upgrade required.',
            'data' => null,
        ];

        if ($errors !== null && $errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
