<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovalsAccess
{
    public function handle(Request $request, Closure $next): Response
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

        if ($plan === null || ! $plan->approvals_enabled || $plan->approval_levels_limit === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upgrade required to access approvals.',
                'data' => null,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        $levels = $request->input('levels_json');

        if (is_array($levels) && count($levels) > $plan->approval_levels_limit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upgrade required for the requested approval depth.',
                'data' => null,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
