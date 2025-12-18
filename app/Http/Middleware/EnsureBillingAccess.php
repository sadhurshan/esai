<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBillingAccess
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

        $permission = $level === 'write' ? 'billing.write' : 'billing.read';

        $companyId = $user->company_id !== null ? (int) $user->company_id : null;

        if ($companyId === null) {
            $companyId = $this->resolveActiveCompanyId($user->id);

            if ($companyId !== null) {
                $user->forceFill(['company_id' => $companyId])->save();
            }
        }

        if (! $this->permissionRegistry->userHasAny($user, [$permission], $companyId)) {
            return $this->errorResponse('Billing access required.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function resolveActiveCompanyId(int $userId): ?int
    {
        return app('db')->table('company_user')
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->value('company_id');
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
