<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
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
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
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

        if ($plan === null || ! $plan->multi_currency_enabled || ! $plan->tax_engine_enabled) {
            return $this->upgradeRequiredResponse();
        }

        $normalizedScope = strtolower($scope);
        $companyId = (int) $company->id;
        $isReadRequest = in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);

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
            return response()->json([
                'status' => 'error',
                'message' => $normalizedScope === 'billing'
                    ? 'Billing permissions required.'
                    : 'Tenant settings permission required.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
