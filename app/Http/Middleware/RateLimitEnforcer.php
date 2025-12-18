<?php

namespace App\Http\Middleware;

use App\Enums\RateLimitScope;
use App\Services\Admin\RateLimitService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RateLimitEnforcer
{
    public function __construct(private readonly RateLimitService $rateLimitService)
    {
    }

    public function handle(Request $request, Closure $next, string $scope = 'api')
    {
        $enum = RateLimitScope::tryFrom($scope) ?? RateLimitScope::Api;

        $companyId = $request->attributes->get('auth.company_id');

        if ($companyId === null && $request->user()) {
            $companyId = $request->user()->company_id;
        }

        if (! $this->rateLimitService->hit($companyId !== null ? (int) $companyId : null, $enum)) {
            return $this->tooManyRequestsResponse();
        }

        return $next($request);
    }

    private function tooManyRequestsResponse(): JsonResponse
    {
        return ApiResponse::error('Rate limit exceeded.', 429);
    }
}
