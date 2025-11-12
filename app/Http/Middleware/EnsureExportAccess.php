<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureExportAccess
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

        if ($plan === null || ! $plan->exports_enabled) {
            return $this->upgradeRequiredResponse([
                'code' => 'exports_disabled',
            ], 'Upgrade required to access data exports.');
        }

        return $next($request);
    }
}
