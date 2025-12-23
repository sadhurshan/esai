<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
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
            return ApiResponse::error('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->hasAnalyticsAccess($user)) {
            return ApiResponse::error('Analytics role required.', Response::HTTP_FORBIDDEN);
        }

        $company = $this->resolveCompanyForAnalytics($user);

        if (! $company instanceof Company) {
            return ApiResponse::error('Company context required.', Response::HTTP_FORBIDDEN);
        }

        if (! $company->relationLoaded('plan')) {
            $company->load('plan');
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

        $personaCompanyId = ActivePersonaContext::get()?->companyId();
        $companyId = $personaCompanyId ?? ($user->company_id ? (int) $user->company_id : null);

        return $this->permissions->userHasAny($user, self::REQUIRED_PERMISSIONS, $companyId);
    }

    private function resolveCompanyForAnalytics(User $user): ?Company
    {
        $persona = ActivePersonaContext::get();

        if ($persona !== null) {
            $companyId = $persona->companyId();

            if ($companyId !== null) {
                $company = $user->company;

                if ($company instanceof Company && (int) $company->id === $companyId) {
                    if (! $company->relationLoaded('plan')) {
                        $company->load('plan');
                    }

                    return $company;
                }

                return Company::query()->with('plan')->find($companyId);
            }
        }

        $user->loadMissing('company.plan');

        return $user->company;
    }
}
