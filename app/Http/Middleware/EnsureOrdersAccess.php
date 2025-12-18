<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrdersAccess
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function handle(Request $request, Closure $next, string $level = 'read'): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $permission = $level === 'write' ? 'orders.write' : 'orders.read';
        $companyId = $user->company_id !== null ? (int) $user->company_id : null;

        if (! $this->permissionRegistry->userHasAny($user, [$permission], $companyId)) {
            $message = $level === 'write' ? 'Orders write access required.' : 'Orders access required.';

            return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
