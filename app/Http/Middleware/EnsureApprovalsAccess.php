<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovalsAccess
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

        if ($plan === null || ! $plan->approvals_enabled || $plan->approval_levels_limit === 0) {
            return $this->upgradeRequiredResponse([
                'code' => 'approvals_disabled',
            ], 'Upgrade required to use approvals.');
        }

        $levels = $request->input('levels_json');

        if (is_array($levels) && count($levels) > $plan->approval_levels_limit) {
            return $this->upgradeRequiredResponse([
                'code' => 'approval_depth_limit',
                'limit' => $plan->approval_levels_limit,
                'requested' => count($levels),
            ], 'Upgrade required to use approvals.');
        }

        return $next($request);
    }
}
