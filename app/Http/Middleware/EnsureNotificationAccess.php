<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotificationAccess
{
    /**
     * Read-level permissions that implicitly require visibility into at least one module.
     * Using read scopes keeps supplier/buyer collaborators eligible while excluding platform-only roles.
     */
    private const PERMISSIONS = [
        'notifications.read',
        'notifications.manage',
    ];

    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $user->loadMissing('company');
        $company = $user->company;

        if (! $company instanceof Company) {
            return ApiResponse::error('Company context required.', Response::HTTP_FORBIDDEN);
        }

        if (! $this->permissionRegistry->userHasAny($user, self::PERMISSIONS, (int) $company->id)) {
            return ApiResponse::error('Notifications require module read access.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
