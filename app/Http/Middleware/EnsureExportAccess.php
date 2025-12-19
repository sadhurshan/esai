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

class EnsureExportAccess
{
    use RespondsWithPlanUpgrade;

    private const PERMISSIONS = ['orders.read', 'orders.write'];

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

        if ($plan === null || ! $plan->exports_enabled) {
            return $this->upgradeRequiredResponse([
                'code' => 'exports_disabled',
            ], 'Upgrade required to access data exports.');
        }

        $activePersona = ActivePersonaContext::get();

        if ($activePersona?->isSupplier() || str_starts_with((string) $user->role, 'supplier_')) {
            return ApiResponse::error('Orders access required to run exports.', Response::HTTP_FORBIDDEN);
        }

        if (! $this->permissionRegistry->userHasAny($user, self::PERMISSIONS, (int) $company->id)) {
            return ApiResponse::error('Orders access required to run exports.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
