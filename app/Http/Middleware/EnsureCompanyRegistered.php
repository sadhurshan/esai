<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyRegistered
{
    /**
     * Handle an incoming request.
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

        if ($company === null || ! $company->hasCompletedBuyerOnboarding()) {
            if ($request->routeIs('company.registration')) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Complete company onboarding before proceeding.',
                    Response::HTTP_FORBIDDEN,
                    [
                        'company' => ['Company onboarding incomplete.'],
                        'missing_fields' => $company?->buyerOnboardingMissingFields() ?? Company::BUYER_ONBOARDING_REQUIRED_FIELDS,
                    ]
                );
            }

            return redirect()->route('company.registration');
        }

        return $next($request);
    }
}
