<?php

namespace App\Http\Middleware;

use App\Events\FeatureEntitlementChecked;
use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiWorkflowAccess
{
    use RespondsWithPlanUpgrade;

    private const FEATURE_KEY = 'ai_workflows_enabled';

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $company = $this->resolveCompany($user);

        if (! $company instanceof Company) {
            return ApiResponse::error('Company context required.', Response::HTTP_FORBIDDEN);
        }

        $enabled = $this->featureEnabled($company);

        FeatureEntitlementChecked::dispatch(
            self::FEATURE_KEY,
            $enabled,
            $company->id,
            $user->id,
            [
                'method' => $request->getMethod(),
                'path' => $request->path(),
            ],
        );

        if (! $enabled) {
            return $this->upgradeRequiredResponse(
                ['code' => 'ai_workflows_disabled'],
                'AI workflows are not enabled for this workspace.',
                Response::HTTP_PAYMENT_REQUIRED,
            );
        }

        return $next($request);
    }

    private function resolveCompany(User $user): ?Company
    {
        $companyId = ActivePersonaContext::companyId();

        if ($companyId === null && $user->company_id !== null) {
            $companyId = (int) $user->company_id;
        }

        if ($companyId === null) {
            return null;
        }

        return Company::query()
            ->with(['plan', 'featureFlags'])
            ->find($companyId);
    }

    private function featureEnabled(Company $company): bool
    {
        $override = $this->companyFlagOverride($company->featureFlags ?? collect());
        $planEnabled = (bool) ($company->plan?->approvals_enabled ?? false);

        return $override ?? $planEnabled;
    }

    private function companyFlagOverride(Collection $flags): ?bool
    {
        if ($flags->isEmpty()) {
            return null;
        }

        $flag = $flags->firstWhere('key', self::FEATURE_KEY);

        if ($flag === null) {
            return null;
        }

        $value = $flag->value;

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (array_key_exists('enabled', $value)) {
                return (bool) $value['enabled'];
            }

            if (array_key_exists('active', $value)) {
                return (bool) $value['active'];
            }
        }

        return null;
    }
}
