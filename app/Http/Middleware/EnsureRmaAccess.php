<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
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
