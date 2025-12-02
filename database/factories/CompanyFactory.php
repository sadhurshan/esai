<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;
    private const ALLOWED_PLAN_CODES = ['community', 'starter', 'growth', 'enterprise'];

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->randomNumber()),
            'status' => 'active',
            'supplier_status' => 'none',
            'directory_visibility' => 'private',
            'supplier_profile_completed_at' => null,
            'registration_no' => strtoupper($this->faker->bothify('REG-######')),
            'tax_id' => strtoupper($this->faker->bothify('TAX-####')),
            'country' => strtoupper($this->faker->countryCode()),
            'email_domain' => $this->faker->unique()->domainName(),
            'primary_contact_name' => $this->faker->name(),
            'primary_contact_email' => $this->faker->unique()->safeEmail(),
            'primary_contact_phone' => $this->faker->e164PhoneNumber(),
            'address' => $this->faker->streetAddress(),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->url(),
            'region' => $this->faker->randomElement(['us', 'eu', 'asia']),
            'owner_user_id' => null,
            'rfqs_monthly_used' => 0,
            'invoices_monthly_used' => 0,
            'analytics_usage_months' => 0,
            'analytics_last_generated_at' => null,
            'risk_scores_monthly_used' => 0,
            'rma_monthly_used' => 0,
            'credit_notes_monthly_used' => 0,
            'storage_used_mb' => 0,
            'stripe_id' => null,
            'plan_id' => Plan::factory(),
            'plan_code' => null,
            'trial_ends_at' => null,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Company $company): void {
            $this->syncPlanAttributes($company, false);
        })->afterCreating(function (Company $company): void {
            $this->syncPlanAttributes($company, true);
        });
    }

    private function syncPlanAttributes(Company $company, bool $persist): void
    {
        $originalPlanId = $company->plan_id;
        $originalPlanCode = $company->plan_code;

        if ($company->plan_code !== null) {
            $plan = Plan::query()->firstWhere('code', $company->plan_code);

            if ($plan instanceof Plan) {
                $company->plan_id = $plan->id;
                $company->plan_code = $plan->code;

                if ($persist && ($company->plan_id !== $originalPlanId || $company->plan_code !== $originalPlanCode)) {
                    $company->save();
                }

                return;
            }
        }

        if ($company->plan_id !== null) {
            $plan = Plan::query()->find($company->plan_id);

            if ($plan instanceof Plan && $company->plan_code !== $plan->code && $this->isAllowedPlanCode($plan->code)) {
                $company->plan_code = $plan->code;

                if ($persist && $company->plan_code !== $originalPlanCode) {
                    $company->save();
                }
            }
        }
    }

    private function isAllowedPlanCode(?string $code): bool
    {
        return $code !== null && in_array($code, self::ALLOWED_PLAN_CODES, true);
    }
}
