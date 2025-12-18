<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithPlanUpgrade;
use App\Models\Company;
use App\Models\Plan;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDigitalTwinAccess
{
    use RespondsWithPlanUpgrade;

    private const DIGITAL_TWIN_PLAN_CODES = ['growth', 'enterprise'];

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        /** @var Company|null $company */
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->errorResponse('Company context required.', Response::HTTP_FORBIDDEN);
        }

        $company->loadMissing(['plan', 'featureFlags']);
        $plan = $company->plan;

        $flagOverride = $this->digitalTwinFlagOverride($company->featureFlags ?? collect());
        $planEnabled = $plan?->digital_twin_enabled ?? false;
        $tierEnabled = $this->planCodeAllowsDigitalTwins($plan, $company);

        $digitalTwinEnabled = $flagOverride ?? ($planEnabled || $tierEnabled);

        if (! $digitalTwinEnabled) {
            return $this->upgradeRequiredResponse([
                'code' => 'digital_twin_disabled',
            ]);
        }

        if ($this->requiresMaintenance($request) && (! $plan->maintenance_enabled)) {
            return $this->upgradeRequiredResponse([
                'code' => 'maintenance_disabled',
            ]);
        }

        return $next($request);
    }

    private function planCodeAllowsDigitalTwins(?Plan $plan, Company $company): bool
    {
        $planCode = $company->plan_code ?? $plan?->code ?? null;

        if (! is_string($planCode) || $planCode === '') {
            return false;
        }

        return in_array(strtolower($planCode), self::DIGITAL_TWIN_PLAN_CODES, true);
    }

    private function digitalTwinFlagOverride(Collection $flags): ?bool
    {
        if ($flags->isEmpty()) {
            return null;
        }

        $flag = $flags->firstWhere('key', 'digital_twin_enabled');

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

    private function requiresMaintenance(Request $request): bool
    {
        $path = $request->path();

        if (Str::contains($path, 'digital-twin/procedures')) {
            return true;
        }

        if (Str::contains($path, 'digital-twin/assets') && Str::contains($path, 'procedures')) {
            return true;
        }

        return Str::contains($path, 'procedures') && Str::contains($path, 'complete');
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
