<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureMoneyAccess
{
    use RespondsWithPlanUpgrade;

    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function handle(Request $request, Closure $next, string $scope = 'tenant'): JsonResponse|Response
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

        $normalizedScope = strtolower($scope);
        $companyId = (int) $company->id;
        $isReadRequest = in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);

        if (ActivePersonaContext::isSupplier()) {
            if ($normalizedScope !== 'billing' && $isReadRequest) {
                return $next($request);
            }

            return ApiResponse::error('Supplier access does not include money permissions.', Response::HTTP_FORBIDDEN);
        }

        $plan = $company->plan;

        if ($plan === null || ! $plan->multi_currency_enabled || ! $plan->tax_engine_enabled) {
            return $this->upgradeRequiredResponse();
        }

        $allowed = match ($normalizedScope) {
            'billing' => $this->permissionRegistry->userHasAny(
                $user,
                array_merge(
                    $isReadRequest ? ['billing.read'] : ['billing.write'],
                    ['tenant.settings.manage']
                ),
                $companyId
            ),
            default => $this->permissionRegistry->userHasAny($user, ['tenant.settings.manage'], $companyId),
        };

        if (! $allowed) {
            return ApiResponse::error(
                $normalizedScope === 'billing'
                    ? 'Billing permissions required.'
                    : 'Tenant settings permission required.',
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
