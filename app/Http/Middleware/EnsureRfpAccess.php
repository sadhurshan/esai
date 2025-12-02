<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRfpAccess
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

        $permission = $level === 'write' ? 'rfps.write' : 'rfps.read';
        $companyId = $user->company_id !== null ? (int) $user->company_id : null;

        if (! $this->permissionRegistry->userHasAny($user, [$permission], $companyId)) {
            $isWrite = $level === 'write';
            $message = $isWrite
                ? 'Project RFP write access required.'
                : 'Project RFP access required.';

            return $this->errorResponse(
                $message,
                Response::HTTP_FORBIDDEN,
                $isWrite ? 'rfps_write_required' : 'rfps_read_required'
            );
        }

        return $next($request);
    }

    private function errorResponse(string $message, int $status, ?string $code = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ];

        if ($code !== null) {
            $payload['errors'] = ['code' => $code];
        }

        return response()->json($payload, $status);
    }
}
