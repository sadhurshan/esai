<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventAccess
{
    /**
     * Event delivery management sits behind write-level scopes so that only
     * members trusted with integrations can replay or retry payloads.
     */
    private const PERMISSIONS = [
        'events.manage',
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
                'message' => 'Events access requires integration permissions.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
