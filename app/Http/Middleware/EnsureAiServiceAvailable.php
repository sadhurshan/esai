<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiServiceAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            return ApiResponse::error('AI service is disabled.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI service is disabled.'],
            ]);
        }

        if (! $this->hasSharedSecret()) {
            return ApiResponse::error('AI shared secret is not configured.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI shared secret is missing.'],
            ]);
        }

        return $next($request);
    }

    private function isEnabled(): bool
    {
        return (bool) config('ai.enabled', false);
    }

    private function hasSharedSecret(): bool
    {
        $secret = config('ai.shared_secret');

        return is_string($secret) && trim($secret) !== '';
    }
}
