<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureRmaAccess
{
    use RespondsWithPlanUpgrade;

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

        if ($plan === null || ! $plan->rma_enabled) {
            return $this->upgradeRequiredResponse([
                'code' => 'rma_disabled',
            ], 'Upgrade required to access RMAs.');
        }

        $limit = (int) $plan->rma_monthly_limit;

        if ($limit > 0 && (int) $company->rma_monthly_used >= $limit) {
            return $this->upgradeRequiredResponse([
                'code' => 'rma_limit_reached',
                'limit' => $limit,
                'usage' => (int) $company->rma_monthly_used,
            ], 'Upgrade required to file additional RMAs this month.');
        }

        return $next($request);
    }
}
