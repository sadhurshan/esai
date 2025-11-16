<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\User;
use Illuminate\Support\Collection;
use BackedEnum;

class AuthResponseFactory
{
    public function make(User $user, ?string $token = null): array
    {
        $user->loadMissing(['company.plan', 'company.featureFlags']);

        $company = $user->company;

        return [
            'token' => $token,
            'user' => $this->transformUser($user),
            'company' => $company ? $this->transformCompany($company) : null,
            'feature_flags' => $this->transformFeatureFlags($company?->featureFlags),
            'plan' => $company?->plan_code ?? $company?->plan?->code ?? null,
            'requires_plan_selection' => $company ? $this->requiresPlanSelection($company) : false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company_id' => $user->company_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCompany(?Company $company): array
    {
        if ($company === null) {
            return [];
        }

        $status = $company->status;

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        $supplierStatus = $company->supplier_status;

        if ($supplierStatus instanceof BackedEnum) {
            $supplierStatus = $supplierStatus->value;
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'status' => $status,
            'supplier_status' => $supplierStatus,
            'directory_visibility' => $company->directory_visibility,
            'supplier_profile_completed_at' => $company->supplier_profile_completed_at?->toIso8601String(),
            'is_verified' => (bool) $company->is_verified,
            'plan' => $company->plan_code ?? $company->plan?->code ?? null,
            'billing_status' => $company->billingStatus(),
            'requires_plan_selection' => $this->requiresPlanSelection($company),
        ];
    }

    /**
     * @param  Collection<int, CompanyFeatureFlag>|null  $flags
     * @return array<string, bool>
     */
    private function transformFeatureFlags(?Collection $flags): array
    {
        if ($flags === null || $flags->isEmpty()) {
            return [];
        }

        return $flags
            ->mapWithKeys(function (CompanyFeatureFlag $flag) {
                $value = $flag->value;

                if (is_array($value)) {
                    if (array_key_exists('enabled', $value)) {
                        return [$flag->key => (bool) $value['enabled']];
                    }

                    if (array_key_exists('active', $value)) {
                        return [$flag->key => (bool) $value['active']];
                    }
                }

                if (is_bool($value)) {
                    return [$flag->key => $value];
                }

                return [$flag->key => true];
            })
            ->all();
    }

    private function requiresPlanSelection(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        if (! $company->plan_id && ! $company->plan_code) {
            return true;
        }

        return ! in_array($company->billingStatus(), ['active', 'trialing'], true);
    }
}
