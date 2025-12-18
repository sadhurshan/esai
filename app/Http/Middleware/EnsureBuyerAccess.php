<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerAccess
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $companyId = $user->company_id !== null ? (int) $user->company_id : null;

        if (! $this->permissionRegistry->userHasAny($user, ['rfqs.write'], $companyId)) {
            return $this->errorResponse('Buyer access required.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
