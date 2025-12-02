<?php

namespace App\Http\Middleware;

use App\Models\Company;
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
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->loadMissing('company');
        $company = $user->company;

        if (! $company instanceof Company) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company context required.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->permissionRegistry->userHasAny($user, self::PERMISSIONS, (int) $company->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notifications require module read access.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
