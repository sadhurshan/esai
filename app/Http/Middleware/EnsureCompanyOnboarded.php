<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyOnboarded
{
    private const ONBOARDING_PERMISSIONS = ['tenant.settings.manage'];

    public function __construct(private readonly PermissionRegistry $permissions)
    {
    }

    /**
     * Handle an incoming request by enforcing buyer onboarding completion.
     */
    public function handle(Request $request, Closure $next, string $mode = 'default'): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (ActivePersonaContext::isSupplier()) {
            return $next($request);
        }

        if (in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $next($request);
        }

        $user->loadMissing('company');
        $company = $user->company;

        if ($company === null) {
            $companyId = DB::table('company_user')
                ->where('user_id', $user->id)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->orderByDesc('created_at')
                ->value('company_id');

            if ($companyId !== null) {
                $company = Company::query()->find($companyId);

                if ($company !== null) {
                    $user->forceFill(['company_id' => $companyId])->save();
                }
            }
        }

        if ($company === null) {
            return ApiResponse::error(
                'No active company membership.',
                Response::HTTP_FORBIDDEN,
                [
                    'company' => ['You are not assigned to any company. Request a new invitation or contact your administrator.'],
                ]
            );
        }

        if (! $company instanceof Company) {
            return $next($request);
        }

        if ($company->hasCompletedBuyerOnboarding()) {
            return $next($request);
        }

        $strict = $mode === 'strict';

        if (! $strict && $this->userHasOnboardingPermission($user, $company)) {
            return $next($request);
        }

        return ApiResponse::error(
            'Company onboarding incomplete.',
            Response::HTTP_FORBIDDEN,
            [
                'company' => ['Company onboarding incomplete.'],
                'missing_fields' => $company->buyerOnboardingMissingFields(),
            ]
        );
    }

    private function userHasOnboardingPermission(User $user, Company $company): bool
    {
        return $this->permissions->userHasAny(
            $user,
            self::ONBOARDING_PERMISSIONS,
            (int) $company->getKey()
        );
    }
}
