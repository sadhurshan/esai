<?php

namespace App\Http\Middleware;

use App\Enums\PlatformAdminRole;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminGuard
{
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->deny(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $role = null;

        if ($requiredRole !== null) {
            $role = PlatformAdminRole::tryFrom($requiredRole);
        }

        if ($role === null) {
            $role = PlatformAdminRole::Support;
        }

        if (! $user->isPlatformAdmin($role)) {
            return $this->deny(Response::HTTP_FORBIDDEN, 'Administrator access required.');
        }

        return $next($request);
    }

    private function deny(int $status, string $message): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
