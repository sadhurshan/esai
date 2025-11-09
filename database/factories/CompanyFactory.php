<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

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
            'storage_used_mb' => 0,
            'stripe_id' => null,
            'plan_code' => 'starter',
            'trial_ends_at' => null,
        ];
    }
}
