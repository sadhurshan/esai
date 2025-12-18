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

class EnsureSupplierInvoicingAccess
{
    use RespondsWithPlanUpgrade;

    private const FEATURE_KEY = 'supplier_invoicing_enabled';
    private const PLAN_CODES = ['growth', 'enterprise'];

    public function handle(Request $request, Closure $next, string $level = 'read'): JsonResponse|Response
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
                'level' => $level,
                'method' => $request->getMethod(),
                'path' => $request->path(),
            ],
        );

        if (! $enabled) {
            return $this->upgradeRequiredResponse(
                ['code' => 'supplier_invoicing_disabled'],
                'Supplier-authored invoicing is disabled for this workspace.',
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
        $planEnabled = (bool) ($company->plan?->supplier_invoicing_enabled ?? false);
        $codeEnabled = $this->planCodeAllows($company);

        return $override ?? ($planEnabled || $codeEnabled);
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

    private function planCodeAllows(Company $company): bool
    {
        $code = $company->plan_code ?? $company->plan?->code ?? null;

        if (! is_string($code) || $code === '') {
            return false;
        }

        return in_array(strtolower($code), self::PLAN_CODES, true);
    }
}
