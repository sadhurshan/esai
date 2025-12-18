<?php

namespace App\Http\Middleware\Concerns;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait RespondsWithPlanUpgrade
{
    protected function upgradeRequiredResponse(?array $errors = null, ?string $message = null, int $status = Response::HTTP_PAYMENT_REQUIRED): JsonResponse
    {
        return ApiResponse::error($message ?? 'Upgrade required.', $status, $errors);
    }
}
