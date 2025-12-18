<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureSearchAccess
{
    use RespondsWithPlanUpgrade;

    private const PERMISSIONS = [
        'search.use',
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

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            return ApiResponse::error('Company context required.', Response::HTTP_FORBIDDEN);
        }

        $plan = $company->plan;

        if ($plan === null || ! $plan->global_search_enabled) {
            return $this->upgradeRequiredResponse([
                'code' => 'search_disabled',
            ], 'Global search is disabled for this plan.', Response::HTTP_FORBIDDEN);
        }

        if (! $this->permissionRegistry->userHasAny($user, self::PERMISSIONS, (int) $company->id)) {
            return ApiResponse::error('Search access requires read permissions.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
