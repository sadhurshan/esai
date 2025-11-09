<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyOnboarded
{
    /**
     * Handle an incoming request by enforcing buyer onboarding completion.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $next($request);
        }

        $user->loadMissing('company');
        $company = $user->company;

        if ($company instanceof Company && $company->hasCompletedBuyerOnboarding()) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Complete company onboarding before performing this action.',
            'data' => null,
            'errors' => [
                'company' => ['Company onboarding incomplete.'],
                'missing_fields' => $company?->buyerOnboardingMissingFields() ?? Company::BUYER_ONBOARDING_REQUIRED_FIELDS,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
