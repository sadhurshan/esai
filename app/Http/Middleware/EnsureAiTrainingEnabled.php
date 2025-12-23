<?php

namespace App\Http\Middleware;

use App\Events\FeatureEntitlementChecked;
use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Models\ModelTrainingJob;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiTrainingEnabled
{
    use RespondsWithPlanUpgrade;

    private const FEATURE_KEY = 'ai_training_enabled';

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isPlatformSuper()) {
            return $next($request);
        }

        $company = $this->resolveTargetCompany($request, $user);

        if (! $company instanceof Company) {
            return $next($request);
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
                ['code' => 'ai_training_disabled'],
                'AI training is not enabled for this workspace.',
                Response::HTTP_PAYMENT_REQUIRED,
            );
        }

        return $next($request);
    }

    private function resolveTargetCompany(Request $request, User $user): ?Company
    {
        $companyId = $request->input('company_id');

        if (is_numeric($companyId)) {
            return $this->findCompany((int) $companyId);
        }

        $job = $request->route('model_training_job');

        if ($job instanceof ModelTrainingJob) {
            return $this->findCompany($job->company_id);
        }

        return $this->resolveCompanyFromContext($user);
    }

    private function resolveCompanyFromContext(User $user): ?Company
    {
        $companyId = ActivePersonaContext::companyId();

        if ($companyId === null && $user->company_id !== null) {
            $companyId = (int) $user->company_id;
        }

        if ($companyId === null) {
            return null;
        }

        return $this->findCompany($companyId);
    }

    private function findCompany(?int $companyId): ?Company
    {
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

        if ($override !== null) {
            return $override;
        }

        return $this->planAllowsTraining($company);
    }

    private function planAllowsTraining(Company $company): bool
    {
        $planCode = $company->plan?->code ?? $company->plan_code;
        $allowedCodes = $this->allowedPlanCodes();

        if ($allowedCodes === []) {
            return (bool) config('plans.features.'.self::FEATURE_KEY.'.default', false);
        }

        if (! is_string($planCode) || $planCode === '') {
            return false;
        }

        return in_array(strtolower($planCode), $allowedCodes, true);
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

    /**
     * @return list<string>
     */
    private function allowedPlanCodes(): array
    {
        $codes = config('plans.features.'.self::FEATURE_KEY.'.plan_codes', []);

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($code): string => is_string($code) ? strtolower(trim($code)) : '',
            $codes,
        ), static fn (string $code): bool => $code !== '')));
    }
}
