<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnalyticsAccess
{
    use RespondsWithPlanUpgrade;

    /**
     * Permissions that unlock analytics access for tenant members.
     *
     * @var list<string>
     */
    private const REQUIRED_PERMISSIONS = [
        'analytics.read',
        'analytics.generate',
    ];

    public function __construct(private readonly PermissionRegistry $permissions)
    {
    }

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'status' => 'errors',
                'message' => 'Authentication required.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->hasAnalyticsAccess($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Analytics role required.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company context required.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $plan = $company->plan;

        if ($plan === null) {
            return $this->upgradeRequiredResponse();
        }

        if ($plan->code === 'community') {
            return $next($request);
        }

        if (! $plan->analytics_enabled) {
            return $this->upgradeRequiredResponse(
                null,
                'Current plan does not include analytics features.'
            );
        }

        return $next($request);
    }

    private function hasAnalyticsAccess(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $companyId = $user->company_id ? (int) $user->company_id : null;

        return $this->permissions->userHasAny($user, self::REQUIRED_PERMISSIONS, $companyId);
    }
}
