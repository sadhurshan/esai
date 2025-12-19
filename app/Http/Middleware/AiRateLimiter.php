<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AiRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            return $next($request);
        }

        $key = $this->rateLimitKey($request);

        if ($key === null) {
            return $next($request);
        }

        $maxAttempts = $this->requestsPerMinute();
        $decaySeconds = $this->windowSeconds();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($key);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }

    private function tooManyAttemptsResponse(string $key): JsonResponse
    {
        $retryAfter = max(1, RateLimiter::availableIn($key));

        $response = ApiResponse::error('AI rate limit exceeded.', 429, [
            'rate_limit' => ['Too many AI requests. Please retry shortly.'],
        ]);

        $response->headers->set('Retry-After', (string) $retryAfter);

        return $response;
    }

    private function rateLimitKey(Request $request): ?string
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        if (! is_scalar($identifier) || $identifier === '') {
            return null;
        }

        $companyId = $request->attributes->get('company_id');

        if ($companyId === null && $user->company_id !== null) {
            $companyId = $user->company_id;
        }

        $companyKey = $companyId !== null ? (string) $companyId : 'global';

        return sprintf('ai:%s:%s', $companyKey, $identifier);
    }

    private function requestsPerMinute(): int
    {
        $value = (int) config('ai.rate_limit.requests_per_minute', 30);

        return max(1, $value);
    }

    private function windowSeconds(): int
    {
        $value = (int) config('ai.rate_limit.window_seconds', 60);

        return max(1, $value);
    }

    private function isEnabled(): bool
    {
        return (bool) config('ai.rate_limit.enabled', true);
    }
}
