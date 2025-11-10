<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExportAccess
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

        if ($plan === null || ! $plan->data_export_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upgrade required to access data exports.',
                'data' => null,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
