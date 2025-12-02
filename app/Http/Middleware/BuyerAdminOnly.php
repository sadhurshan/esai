<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BuyerAdminOnly
{
    public function __construct(private readonly PermissionRegistry $permissions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $companyId = $user->company_id ? (int) $user->company_id : null;

        if (! $this->permissions->userHasAny($user, ['tenant.settings.manage'], $companyId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant admin permission required.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
