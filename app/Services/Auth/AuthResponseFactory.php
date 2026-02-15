<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Enums\CompanySupplierStatus;
use App\Models\CompanyFeatureFlag;
use App\Models\Plan;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierPersonaService;
use Illuminate\Support\Collection;
use BackedEnum;

class AuthResponseFactory
{
    /**
     * @var list<string>
     */
    private const PLAN_FEATURE_FLAG_COLUMNS = [
        'analytics_enabled',
        'risk_scores_enabled',
        'approvals_enabled',
        'rma_enabled',
        'credit_notes_enabled',
        'supplier_invoicing_enabled',
        'global_search_enabled',
        'quotes_enabled',
        'quote_revisions_enabled',
        'digital_twin_enabled',
        'maintenance_enabled',
        'inventory_enabled',
        'pr_enabled',
        'multi_currency_enabled',
        'tax_engine_enabled',
        'localization_enabled',
        'exports_enabled',
        'data_export_enabled',
    ];

    /**
     * Feature flags derived from plan code tiers.
     *
     * @var array<string, list<string>>
     */
    private const PLAN_CODE_FEATURE_FLAGS = [
        'purchase_orders' => ['growth', 'enterprise'],
        'invoices_enabled' => ['growth', 'enterprise'],
        'digital_twin_enabled' => ['growth', 'enterprise'],
        'supplier_invoicing_enabled' => ['growth', 'enterprise'],
    ];

    /**
     * Derived feature flags that piggyback on RFQ allowances.
     *
     * @var list<string>
     */
    private const RFQ_FEATURE_FLAGS = [
        'rfqs.create',
        'rfqs.publish',
        'rfqs.suppliers.invite',
        'rfqs.attachments.manage',
        'suppliers.directory.browse',
    ];

    public function __construct(
        private readonly PersonaResolver $personaResolver,
        private readonly SupplierPersonaService $supplierPersonaService,
    )
    {
    }

    public function make(User $user, ?string $token = null): array
    {
        $user->loadMissing(['company.plan', 'company.featureFlags']);

        $company = $user->company;
        $this->ensureSupplierPersona($user, $company);
        $personas = $this->personaResolver->resolve($user);

        return [
            'token' => $token,
            'user' => $this->transformUser($user),
            'company' => $company ? $this->transformCompany($company) : null,
            'feature_flags' => $this->featureFlagsForCompany($company),
            'plan' => $company?->plan_code ?? $company?->plan?->code ?? null,
            'requires_plan_selection' => $company ? $this->requiresPlanSelection($company) : false,
            'requires_email_verification' => ! $user->hasVerifiedEmail(),
            'personas' => $personas,
            'active_persona' => $this->personaResolver->determineActivePersona($user, $personas),
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
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'has_verified_email' => $user->hasVerifiedEmail(),
            'job_title' => $user->job_title,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'avatar_url' => $user->avatar_url,
            'avatar' => $user->avatar_url,
            'avatar_path' => $user->avatar_path,
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

        $graceEndsAt = $company->billingGraceEndsAt();
        $billingLockAt = $company->billingLockDate();

        return [
            'id' => $company->id,
            'name' => $company->name,
            'status' => $status,
            'start_mode' => $company->start_mode,
            'supplier_status' => $supplierStatus,
            'directory_visibility' => $company->directory_visibility,
            'supplier_profile_completed_at' => $company->supplier_profile_completed_at?->toIso8601String(),
            'is_verified' => (bool) $company->is_verified,
            'plan' => $company->plan_code ?? $company->plan?->code ?? null,
            'billing_status' => $company->billingStatus(),
            'billing_read_only' => $company->isInBillingGracePeriod(),
            'billing_grace_ends_at' => $graceEndsAt?->toIso8601String(),
            'billing_lock_at' => $billingLockAt?->toIso8601String(),
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

    /**
     * @return array<string, bool>
     */
    private function featureFlagsForCompany(?Company $company): array
    {
        if (! $company instanceof Company) {
            return [];
        }

        $planFlags = $this->planFeatureFlags($company);
        $customFlags = $this->transformFeatureFlags($company->featureFlags);
        $flags = array_merge($planFlags, $customFlags);

        if (config('ai.enabled', false)) {
            $flags['ai.enabled'] = true;
            $flags['ai.copilot'] = true;
            $flags['ai_copilot'] = true;
        }

        return $flags;
    }

    /**
     * @return array<string, bool>
     */
    private function planFeatureFlags(Company $company): array
    {
        $plan = $company->plan;

        if (! $plan instanceof Plan) {
            return [];
        }

        $flags = [];

        foreach (self::PLAN_FEATURE_FLAG_COLUMNS as $column) {
            $flags[$column] = (bool) ($plan->{$column} ?? false);
        }

        $flags['ai_workflows_enabled'] = $flags['approvals_enabled'] ?? (bool) ($plan->approvals_enabled ?? false);

        if (($flags['analytics_enabled'] ?? false) === true) {
            // Frontend gates inventory forecast and supplier scorecards behind analytics.access.
            $flags['analytics.access'] = true;
        }

        $planCode = $plan->code ?? $company->plan_code ?? null;

        if (is_string($planCode) && $planCode !== '') {
            $normalizedCode = strtolower($planCode);

            foreach (self::PLAN_CODE_FEATURE_FLAGS as $flag => $allowedPlanCodes) {
                $planAllowsFlag = in_array($normalizedCode, $allowedPlanCodes, true);
                $existingValue = $flags[$flag] ?? false;

                $flags[$flag] = $existingValue || $planAllowsFlag;
            }

            $flags = array_merge($flags, $this->planConfigFeatureFlags($normalizedCode));
        }

        if ($plan->rfqs_per_month !== null) {
            foreach (self::RFQ_FEATURE_FLAGS as $flag) {
                $flags[$flag] = true;
            }
        }

        return $flags;
    }

    /**
     * @return array<string, bool>
     */
    private function planConfigFeatureFlags(string $planCode): array
    {
        $features = config('plans.features', []);

        if (! is_array($features) || $features === []) {
            return [];
        }

        $flags = [];

        foreach ($features as $flag => $options) {
            if (! is_array($options)) {
                continue;
            }

            $allowedPlanCodes = $options['plan_codes'] ?? [];
            $enabledByDefault = (bool) ($options['default'] ?? false);

            if (! is_array($allowedPlanCodes) || $allowedPlanCodes === []) {
                if ($enabledByDefault) {
                    $flags[$flag] = true;
                }

                continue;
            }

            $normalizedPlans = array_values(array_filter(array_map(
                static fn ($code): string => is_string($code) ? strtolower(trim($code)) : '',
                $allowedPlanCodes,
            ), static fn (string $code): bool => $code !== ''));

            if (in_array($planCode, $normalizedPlans, true)) {
                $flags[$flag] = true;
            }
        }

        return $flags;
    }

    private function requiresPlanSelection(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }
        $supplierStatus = $company->supplier_status;

        if ($company->start_mode === 'supplier') {
            return false;
        }

        if (
            ($supplierStatus instanceof CompanySupplierStatus && $supplierStatus !== CompanySupplierStatus::None)
            || (is_string($supplierStatus) && $supplierStatus !== CompanySupplierStatus::None->value)
        ) {
            return false;
        }

        return ! $company->plan_id && ! $company->plan_code;
    }

    private function ensureSupplierPersona(User $user, ?Company $company): void
    {
        if (! $company instanceof Company) {
            return;
        }

        if ($company->start_mode !== 'supplier') {
            return;
        }

        $supplier = Supplier::query()->where('company_id', $company->id)->first();

        if (! $supplier instanceof Supplier) {
            $supplier = new Supplier([
                'company_id' => $company->id,
                'name' => $company->name,
                'status' => 'pending',
                'email' => $company->primary_contact_email,
                'phone' => $company->primary_contact_phone,
                'address' => $company->address,
                'country' => $company->country,
                'website' => $company->website,
                'capabilities' => [],
            ]);

            $supplier->save();
        }

        $this->supplierPersonaService->ensureBuyerContact($supplier, $company->id, $company, false);
    }
}
