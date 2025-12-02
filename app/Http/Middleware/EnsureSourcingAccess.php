<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSourcingAccess
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

        $permission = $level === 'write' ? 'rfqs.write' : 'rfqs.read';
        $companyId = $user->company_id !== null ? (int) $user->company_id : null;

        if (! $this->permissionRegistry->userHasAny($user, [$permission], $companyId)) {
            $message = $level === 'write'
                ? 'Sourcing write access required.'
                : 'Sourcing access required.';

            return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
