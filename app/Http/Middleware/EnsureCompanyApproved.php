<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyApproved
{
    /**
     * Ensure the authenticated company has been approved by platform operations before continuing.
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

        if (! $company instanceof Company) {
            return $this->pendingResponse();
        }

        if ($company->status === CompanyStatus::Suspended) {
            return response()->json([
                'status' => 'error',
                'message' => 'This company is currently suspended.',
                'data' => null,
                'errors' => [
                    'company' => ['Account suspended. Contact support to resolve billing or compliance issues.'],
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->companyIsApproved($company)) {
            return $next($request);
        }

        return $this->pendingResponse();
    }

    private function companyIsApproved(?Company $company): bool
    {
        if (! $company instanceof Company) {
            return false;
        }

        return in_array($company->status, [CompanyStatus::Active, CompanyStatus::Trial], true);
    }

    private function pendingResponse(): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Your company must be approved by Elements Supply operations before performing this action.',
            'data' => null,
            'errors' => [
                'company' => ['Company approval pending. A platform admin must verify your documents first.'],
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
